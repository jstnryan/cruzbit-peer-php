<?php

namespace cruzbit;

use cruzbit\messages\BlockMessage;
use cruzbit\messages\FindCommonAncestorMessage;
use cruzbit\messages\GetBlockMessage;
use cruzbit\messages\InvBlockMessage;
use cruzbit\messages\Message;
use cruzbit\messages\PeerAddressesMessage;
use cruzbit\types\Block;
use cruzbit\types\BlockHeader;
use Ratchet\Client\WebSocket;
use Ratchet\RFC6455\Messaging\MessageInterface;

class Peer {

    // Maximum blocks per inv_block message
    const maxBlocksPerInv = 500;
    // Maximum local inflight queue size
    const inflightQueueMax = 8;
    // Maximum local download queue size
    const downloadQueueMax = self::maxBlocksPerInv * 10;

    protected $genesisID = '00000000e29a7850088d660489b7b9ae2da763bc3bd83324ecc54eee04840adb';
    protected $lastNewBlockTime = null;
    // Last ancestor block, used when syncing blocks with peer
    protected $continuationBlockID = null;

    /** @var Database */
    protected $db;
    /** @var Log */
    protected $log;
    /** @var Queue */
    protected $localDownloadQueue;
    /** @var UniqueList */
    protected $localInflightQueue;
    /** @var Queue */
    protected $messageQueue;
    /** @var WebSocket */
    protected $connection = null;

    public function __construct(Database $db, Log $log) {
        $this->db = $db;
        $this->log = $log;
        $this->localDownloadQueue = new Queue;
        $this->localInflightQueue = new UniqueList;
        $this->messageQueue = new Queue;
    }

    public function storeConnection(WebSocket $connection) {
        $this->connection = $connection;
    }

    public function onMessage(MessageInterface $msg) {
// Requires PHP v7.3
//        try {
//            $message = json_decode("{$msg}", true, 512, JSON_THROW_ON_ERROR);
//            //we could also decode directly to a Message object, however we
//            // don't really need to do any object manipulation on this, just get
//            // its parts, and so we can just use an array
//            //$message = new Message(json_decode("{$msg}", true, 512, JSON_THROW_ON_ERROR));
//        } catch (\JsonException $e) {
//            $this->log->write(2, sprintf("Error decoding message: %s", $e->getMessage()));
//            return true;
//        }
        $message = json_decode("{$msg}", true);
        if ($message === null) {
            $this->log->write(2, "Error decoding message");
            return true;
        }

        $this->log->write(4, sprintf("onMessage: Handling %s message", $message['type']));

        // hangup if the peer is sending oversized messages
        if (
            isset($message['body']) &&
            $message['type'] !== 'block' &&
            strlen(json_encode($message['body'])) > MAX_PROTOCOL_MESSAGE_LENGTH
        ) {
            $this->log->write(2, sprintf("Received too large (%d bytes) of a '%s' message",
                strlen($message['body']), $message['type']));
            return true;
        }

        switch ($message['type']) {
            case 'inv_block':
                $this->log->write(3, "onMessage: Got inv_block message");
                $inv = new InvBlockMessage($message['body']);
                $block_ids = $inv->getBlockIds();
                $count = count($block_ids);
                for ($i = 0; $i < $count; $i++) {
                    if (!$this->onInvBlock($block_ids[$i], $i, $count)) {
                        break 2;
                        //return false;
                    }
                }
                break;

            case 'get_block':
            case 'get_block_by_height':
                $this->log->write(3, sprintf("Ignoring received message: %s", $message['type']));
                break;

            case 'block':
                $blockmessage = new BlockMessage($message['body']);
                $this->log->write(4, sprintf("onMessage block: %s", print_r($blockmessage, true)));
                $block = $blockmessage->getBlock();
                if (empty($block)) {
                    $this->log->write(2, "Error: received nil block");
                    break;
                }
                if ($this->onBlock(new Block($block))) {
                    $this->lastNewBlockTime = new \DateTime('now', new \DateTimeZone('UTC'));
                }
                break;

            case 'find_common_ancestor':
                $fca = new FindCommonAncestorMessage($message['body']);
                $block_ids = $fca->getBlockIds();
                $num = count($block_ids);
                $this->log->write(4, sprintf("onMessage: find_common_ancestor block count: %d", $num));
                for ($i = 0; $i < $num; $i++) {
                    if ($this->onFindCommonAncestor($block_ids[$i], $i, $num)) {
                        // stop processing when common ancestor found
                        break;
                    }
                }
                break;

            case 'get_block_header':
            case 'get_block_header_by_height':
            case 'get_balance':
            case 'get_public_key_transactions':
            case 'get_transaction':
            case 'get_tip_header':
            case 'push_transaction':
            case 'push_transaction_result':
            case 'filter_load':
            case 'filter_add':
            case 'get_filter_transaction_queue':
                $this->log->write(3, sprintf("Ignoring received message: %s", $message['type']));
                break;

            case 'get_peer_addresses':
                if (!$this->onGetPeerAddresses()) {
                    $this->log->write(2, "Error handling get_peer_addresses message");
                }
                break;

            case 'peer_addresses':
            case 'get_transaction_relay_policy':
            case 'get_work':
            case 'submit_work':
                $this->log->write(3, sprintf("Ignoring received message: %s", $message['type']));
                break;

            default:
                $this->log->write(2, sprintf("Unknown message: %s", $message['type']));
        }

        // process message queue
        $this->processMessageQueue();

        return true;//false to close connection
    }

    /**
     * Handle a message from a peer indicating block inventory available for download
     *
     * @param string $block_id  The BlockID currently being handled
     * @param int    $index     The index of the current BlockID in the InvBlock array
     * @param int    $length    The number of BlockID in the current InvBlock array
     * @return bool
     */
    protected function onInvBlock(string $block_id, int $index, int $length) {
        $this->log->write(2, sprintf("Received inv_block: %s\n", $block_id));

        if ($length > $this::maxBlocksPerInv) {
            $this->log->write(2, sprintf("%d blocks IDs is more than %d maximum per inv_block\n",
                $length, self::maxBlocksPerInv));
            return false;
        }

        // do we have it queued or inflight already?
        if ($this->localDownloadQueue->inQueue($block_id)) {
            $this->log->write(2, sprintf("Block %s is already queued or inflight for download",
                $block_id));
            return false;
        }

        // have we processed it?
        if ($this->db->isBlockIdProcessed($block_id)) {
            $this->log->write(2, sprintf("Already processed block %s", $block_id));
            if ($length > 1 && (($index + 1) == $length)) {
                // we might be on a deep side chain. this will get us the next 500 blocks
                return $this->sendFindCommonAncestor($block_id, false);
            }
            return false;
        }

        if ($this->localDownloadQueue->count() >= self::downloadQueueMax) {
            $this->log->write(2, sprintf("Too many blocks in the download queue %d, max: %d",
                $this->localDownloadQueue->count(), self::downloadQueueMax));
            // don't return an error just stop adding them to the queue
            return true;
        }

        // add block to download queue
        $this->log->write(4, sprintf("onInvBlock: Adding block %s to download queue", $block_id));
        $this->localDownloadQueue->push($block_id);

        // process the download queue
        return $this->processDownloadQueue();
    }

    /**
     * Handle receiving a block from a peer.
     *  Returns true if the block was newly processed and accepted.
     *
     * @param Block $block
     * @return bool
     */
    protected function onBlock(Block $block) {
        $this->log->write(4, sprintf("onBlock: Processing block %s", $block->getBlockId()));
        // the message has the ID in it but we can't trust that.
        // it's provided as convenience for trusted peering relationships only
        $block_id = $block->getBlockId();

        $this->log->write(2, sprintf("Received block: %s", $block_id));

        if (!$this->localInflightQueue->inQueue($block_id)) {
            // received an unrequested block
            $this->log->write(1, "Received unrequested block");
            return false;
        }

        $accepted = false;

        // is it an orphan?
        $header = $this->db->getBlockHeader($block->getHeader()->getPrevious());
        if (empty($header)) {
            $this->localInflightQueue->pull($block_id);

            $this->log->write(2, sprintf("Block %s is an orphan, sending find_common_ancestor",
                $block_id));

            // send a find common ancestor request
            $this->sendFindCommonAncestor(null, false);
        } else {
            // process the block
            //TODO: do all of this in a SQL transaction
            if (!$this->db->storeBlock($block_id, $block->getHeader())) {
                return false;
            }
            foreach ($block->getTransactions() as $transaction) {
                $tx = $transaction->toArray();
                unset($tx['signature']);
                $tx_id = hash('sha3-256', json_encode($tx));
                if ($this->db->storeTransaction($tx_id, $transaction)) {
                    $this->db->linkTransaction($block->getHeader()->getHeight(), $block_id, $tx_id);
                }
            }

            $accepted = true;

            $this->localInflightQueue->pull($block_id);
        }

        //$this->processDownloadQueue();

        return $accepted;
    }

    /**
     * Send a message to look for a common ancestor with a peer
     * Might be called from reader or writer context. writeNow means we're in the writer context
     *
     * @param      $start_id
     * @param bool $sendNow
     * @return bool
     */
    public function sendFindCommonAncestor(string $start_id = null, bool $sendNow = false) {
        $this->log->write(2, "Sending find_common_ancestor.");

        if ($start_id === null) {
            $start_id = $this->db->getLocalTip(1, true);
            if ($start_id === false) {
                $this->log->write(4, "sendFindCommonAncestor: could not get local tip");
                return false;
            }
        }

        $height = $this->db->getBlockHeight($start_id);

        $id = $start_id;
        $ids = [];
        $step = 1;
        while ($id !== null) {
            if ($id === $this->genesisID) {
                break;
            }
            $ids[] = $id;
            $depth = $height - $step;
            if ($depth <= 0) {
                break;
            }
            $id = $this->db->getBlockIdForHeight($depth);
            if ($id === false) {
                $this->log->write(2, "Error building find_common_ancestor message.");
                return false;
            }
            if (count($ids) > 10) {
                $step *= 2;
            }
            $height = $depth;
        }
        $ids[] = $this->genesisID;

        $m = new FindCommonAncestorMessage([
            'block_ids' => $ids
        ]);

        if ($sendNow) {
            $this->log->write(4, sprintf("sendFindCommonAncestor: sending message with %d IDs", count($ids)));
            return $this->sendMessage($m);
        } else {
            $this->log->write(4, sprintf("sendFindCommonAncestor: queueing message with %d IDs", count($ids)));
            $this->messageQueue->push($m);
            return true;
        }
    }

    /**
     * Handle a find common ancestor message from a peer
     *
     * @param string $id
     * @param int    $index
     * @param int    $length
     * @return bool
     */
    protected function onFindCommonAncestor(string $id, int $index, int $length) {
        $this->log->write(2, sprintf("Received find_common_ancestor: %s, index: %d, length: %d",
            $id, $index, $length));

        $header = $this->db->getBlockHeader($id);
        if ($header === false || $header === null) {
            // don't have it
            return false;
        }
        $header = new BlockHeader($header);
        //TODO: check branch type

        $this->log->write(2, sprintf("Common ancestor found: %s, height: %d", $id, $header->getHeight()));

        $ids = [];
        $height = $header->getHeight() + 1;
        while (count($ids) < self::maxBlocksPerInv) {
            $nextID = $this->db->getBlockIdForHeight($height);
            if ($nextID === false) {
                return false;
            }
            if ($nextID === null) {
                break;
            }
            $this->log->write(2, sprintf("Queueing inv for block %s, height: %d", $nextID, $height));
            $ids[] = $nextID;
            $height += 1;
        }

        if (count($ids) > 0) {
            // save the last ID so after the peer requests it we can trigger it to
            // send another find common ancestor request to finish downloading the rest of the chain
//PHP >= 7.3.0
//            $this->continuationBlockID = $ids[array_key_last($ids)];
            $this->continuationBlockID = $ids[array_keys($ids)[count($ids)-1]];
            $this->log->write(2, sprintf("Sending inv_block with %d IDs, continuation block: %s",
                count($ids), $this->continuationBlockID));
            $this->messageQueue->push(new InvBlockMessage(['block_ids' => $ids]));
        }
        return true;
    }

    /**
     * Received a request for peer addresses
     */
    protected function onGetPeerAddresses() {
        $this->log->write(2, "Received get_peer_addresses message");

        // get up to 32 peers that have been connected to within the last 3 hours
        $addresses = []; //TODO: actually get addresses when we open more than one connection

        if (count($addresses) != 0) {
            $this->log->write(3, sprintf("Queueing peer_addresses message with %d addresses", count($addresses)));
            $this->sendMessage(new PeerAddressesMessage(['addresses' => $addresses]));
        }
        return true;
    }

    /**
     * Try requesting blocks that are in the download queue
     *
     * @return bool
     */
    protected function processDownloadQueue() {
        $this->log->write(4, sprintf("Processing download queue with %d blocks", $this->localDownloadQueue->count()));
        // fill up as much of the inflight queue as possible
        $queued = 0;
        while ($this->localInflightQueue->count() < self::inflightQueueMax) {
            // next block to download
            $blockToDownload = $this->localDownloadQueue->shift();
            if ($blockToDownload === null) {
                // no more blocks in the queue
                break;
            }

            // double-check if it's been processed since we last checked
            if (is_int($blockToDownload)) {
                $processed = $this->db->getBlockIdForHeight($blockToDownload);
            } else {
                $processed = $this->db->getBlockHeight($blockToDownload);
            }
            if ($processed === false) {
                $this->log->write(1, sprintf("Error attempting to get block information for block: %s",
                    $blockToDownload));
                continue;
            } elseif ($processed !== null) {
                $this->log->write(2, sprintf("Block %s has been processed, removing from download queue",
                    is_string($blockToDownload) ? $blockToDownload : $processed));
                continue;
            }

            $blockToDownload = is_string($blockToDownload) ? $blockToDownload : $processed;

            // mark it inflight locally
            $this->localInflightQueue->push($blockToDownload);
            $queued++;

            // request it
            $this->log->write(2, sprintf("Sending get_block for %s", $blockToDownload));
            $this->messageQueue->push(new GetBlockMessage(['block_id' => $blockToDownload]));
        }

        if ($queued > 0) {
            $this->log->write(2, sprintf("Requested %d block(s) for download", $queued));
            $this->log->write(2, sprintf("Queue size: %d, inflight: %d",
                $this->localDownloadQueue->count(), $this->localInflightQueue->count()));
        }

        return true;
    }

    /**
     * Send pending messages in the message queue
     *
     * @return bool
     */
    protected function processMessageQueue() {
        $this->processDownloadQueue();

        $this->log->write(4, sprintf("processMessageQueue: Processing queue with %d messages", $this->messageQueue->count()));
        while ($this->messageQueue->count() > 0) {
            /** @var \cruzbit\messages\MessageInterface $next */
            $next = $this->messageQueue->shift();
            $this->log->write(3, sprintf("Sending %s message", $next->getType()));
            if (!$this->sendMessage($next)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Construct a Message from MessageInterface object, and immediately send to peer
     *
     * @param \cruzbit\messages\MessageInterface $message
     * @return bool
     */
    protected function sendMessage($message) {
        if ($this->connection === null) {
            $this->log->write(0, "Can't send message; no socket shared with peer.");
            return false;
        }

        $this->log->write(4, sprintf("sendMessage: Sending message %s now", $message->getType()));
        $this->connection->send(json_encode((new Message(['body' => $message]))->toArray()));
        return true;
    }

}
