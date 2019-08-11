<?php

error_reporting(-1);
ini_set('error_log', 'syslog');
ini_set('html_errors', false);
ini_set('display_errors', true);
ini_set('date.timezone', 'Etc/UTC');
date_default_timezone_set('Etc/UTC');

const PEER_ADDR = 'dns.cruzb.it:8831';
const GENESIS_BLOCK = '00000000e29a7850088d660489b7b9ae2da763bc3bd83324ecc54eee04840adb';
const PROTOCOL = 'cruzbit.1';
const ORIGIN = 'https://cruzb.it';

const SQL_HOST = 'localhost';
const SQL_USER = 'user';
const SQL_PASS = 'password';
const SQL_DB = 'blockchain';

$settings = [
    'silent' => false,
    'quiet' => false,
    'verbose' => false,
];

if (PHP_SAPI === 'cli' || PHP_SAPI === 'cli-server') {
    if ($_SERVER['argc'] > 1) {
        $args = $_SERVER['argv'];
        array_shift($args); //don't care about the script name
        foreach ($args as $arg) {
            switch ($arg) {
                case '-s':
                case '--silent':
                    $settings['silent'] = true;
                    $settings['quiet'] = true;
                    $settings['verbose'] = false;
                    break;
                case '-q':
                case '--quiet':
                    $settings['quiet'] = true;
                    break;
                case '-v':
                case '--verbose':
                    $settings['verbose'] = true;
                    break;
                default:
                    echo 'Unknown argument "' . $arg .'"';
                    die;
            }

        }
    }
} else {
    echo 'This script can not be run through a browser window.';
    die;
}

use Ratchet\Client\WebSocket;
use Ratchet\RFC6455\Messaging\MessageInterface;
use React\EventLoop\Factory;
use React\Socket\Connector;

require __DIR__ . '/vendor/autoload.php';

$synced = false;    //currently downloading previous blocks?
$msgQueue = [];     //FIFO by honor system
$local_tip = null;  //block_id of tip (most recent block) in local chain

$onConnect = function(WebSocket $conn) {
    $conn->on('close', function($code = null, $reason = null) {
        echo "Connection closed ({$code} - {$reason})\n";
    });

    $conn->on('message', function(MessageInterface $msg) use($conn) {
        global $settings, $msgQueue, $local_tip, $synced;
        $message = json_decode("{$msg}", true);
        switch ($message['type']) {
            case 'block':
                if($settings['verbose']) echo 'Got message block: ' . $message['body']['block']['header']['height'] . "\n";
                //store block, and its transactions
                storeBlock($message['body']['block_id'], $message['body']['block']['header']);
                foreach ($message['body']['block']['transactions'] as $transaction) {
                    $tx = $transaction;
                    unset($tx['signature']);
                    $tx_id = hash('sha3-256', json_encode($tx));
                    storeTransaction($tx_id, $transaction);
                    linkTransaction($message['body']['block']['header']['height'], $message['body']['block_id'], $tx_id);
                }
                $local_tip = getLocalTip()[0];
                break;
            case 'block_header':
                if($settings['verbose']) echo 'Got message block_header: ' . $message['body']['header']['height'] . "\n";
                //request full block
                $msgQueue[] = createMessage('get_block', $message['body']['block_id']);
                break;
            case 'find_common_ancestor':
                if($settings['verbose']) echo "Got message find_common_ancestor\n";
                $unseenBlocks = findUnseenBlocks($message['body']['block_ids']);
//                foreach ($unseenBlocks as $block_id) {
//                    $msgQueue[] = createMessage('get_block', $block_id);
//                }
                if (count($unseenBlocks) === count($message['body']['block_ids'])) {
                    //none of the offered blocks matched local, so we're clearly not sync'ed
                    // doesn't make sense to start downloading more recent blocks, until we get the previous
                    array_unshift($msgQueue, createMessage('find_common_ancestor', getLocalTip(25)));
                } elseif (count($unseenBlocks) === 0) {
                    $synced = true;
                }
                break;
            case 'get_peer_addresses':
                if($settings['verbose']) echo "Got message get_peer_addresses\n";
                //Full peer functionality; respond with empty PeerAddressesMessage
                //$msgQueue[] = createMessage('peer_addresses', ["192.168.1.14:8831"]);
                break;
            case 'inv_block':
                if($settings['verbose']) echo "Got message inv_block\n";
                if ($message['body']['block_ids'][0] === $local_tip) break;
                $unseenBlocks = findUnseenBlocks($message['body']['block_ids']);
                foreach ($unseenBlocks as $block_id) {
                    $msgQueue[] = createMessage('get_block', $block_id);
                }
                break;
            case 'peer_addresses':
                if($settings['verbose']) echo "got message peer_addresses\n";
                //log peers for network stats?
                break;
            case 'tip_header':
                if($settings['verbose']) echo "Got message get_tip_header\n";
                if ($message['body']['block_id'] !== $local_tip) {
                    $msgQueue[] = createMessage('find_common_ancestor', getLocalTip(25));
                }
                break;
            case 'transaction':
                if($settings['verbose']) echo "Got message transaction\n";
                $tx = $message['body']['transaction'];
                unset($tx['signature']);
                $hash = hash('sha3-256', $tx);
                //https://emn178.github.io/online-tools/sha3_256.html
                break;
            default:
                if($settings['verbose']) echo "Got unknown message:\n";
                echo "\n\n";
                print_r($message);
        }

        if($settings['verbose']) {
            echo 'Message queue: ' . count($msgQueue) . "\n";
            echo print_r($msgQueue, true);
        }
        if (count($msgQueue)) {
            $nextMsg = array_shift($msgQueue);
            if ($settings['verbose']) echo 'Sending message: ' . json_decode($nextMsg, true)['type'] . "\n";
            $conn->send($nextMsg);
        }
    });
};

$dsn = 'mysql:host=' . SQL_HOST . ';dbname=' . SQL_DB;
$opt = [
    PDO::ATTR_ERRMODE               =>  PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE    =>  PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES      =>  false,
];
try {
    $pdo = new PDO($dsn, SQL_USER, SQL_PASS, $opt);
} catch (\PDOException $e) {
    if (!$settings['silent']) echo 'cruzbit peer couldn\'t connect to DB, PDOException: ' . $e->getMessage() . "\n";
    error_log('cruzbit peer couldn\'t connect to DB, PDOException: ' . $e->getMessage());
    die;
}

$loop = Factory::create();
$reactConnector = new Connector($loop, [
    'dns' => '1.1.1.1',
    'timeout' => 10
]);
$connector = new \Ratchet\Client\Connector($loop, $reactConnector);

$connector('wss://' . PEER_ADDR . '/' . GENESIS_BLOCK, [PROTOCOL], ['Origin' => ORIGIN])
->then($onConnect, function(\Exception $e) use ($loop) {
    echo "Could not connect: {$e->getMessage()}\n";
    $loop->stop();
});

$loop->run();

function createMessage($type, ...$val) {
    switch ($type) {
        case 'find_common_ancestor':
            //requires []block_id
            $msg = '{"type":"find_common_ancestor","body":{"block_ids":["' . implode('","', $val[0]) . '"]}}';
            break;
        case 'get_block':
            //requires a block_id
            $msg = '{"type":"get_block","body":{"block_id":"' . $val[0] . '"}}';
            break;
        case 'peer_addresses':
            //requires []PeerAddress
            $msg = '{"type":"peer_addresses","body":{"addresses":["' . implode('","', $val[0]) . '"]}}';
            break;
        default:
            throw new \Exception('Message type not recognized.');
    }
    return $msg;
}

function storeBlock($block_id, $header) {// use ($pdo) {
    global $pdo, $settings;
    $query = $pdo->prepare('
        INSERT INTO `blocks`
            (`block_id`, `previous`, `hash_list_root`, `time`, `target`, `chain_work`, `nonce`, `height`, `transaction_count`)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE block_id = block_id
    ');
    try {
        $success = $query->execute([
            $block_id,
            $header['previous'],
            $header['hash_list_root'],
            $header['time'],
            $header['target'],
            $header['chain_work'],
            $header['nonce'],
            $header['height'],
            $header['transaction_count']
        ]);
        if ($settings['verbose']) echo 'Store block at height ' . $header['height'] . (($success) ? ' successful.' : ' failed.') . "\n";
    } catch (\PDOException $e) {
        if (!$settings['silent']) echo 'cruzbit peer couldn\'t store block, PDOException: ' . $e->getMessage() . "\n";
        error_log('cruzbit peer couldn\'t store block, PDOException: ' . $e->getMessage());
    }
}

function storeTransaction($transaction_id, $transaction) {
    global $pdo, $settings;
    $query = $pdo->prepare('
        INSERT INTO `transactions`
            (`transaction_id`, `time`, `nonce`, `from`, `to`, `amount`, `fee`, `memo`, `matures`, `expires`, `series`, `signature`)
        VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE transaction_id = transaction_id
    ');
    try {
        $success = $query->execute([
            $transaction_id,
            $transaction['time'],
            $transaction['nonce'],
            $transaction['from'] ?? null,
            $transaction['to'],
            $transaction['amount'],
            $transaction['fee'] ?? null,
            $transaction['memo'] ?? null,
            $transaction['matures'] ?? null,
            $transaction['expires'] ?? null,
            $transaction['series'] ?? null,
            $transaction['signature'] ?? null
        ]);
        if ($settings['verbose']) echo 'Store transaction ' . (($success) ? ' successful.' : ' failed.') . "\n";
    } catch (\PDOException $e) {
        if (!$settings['silent']) echo 'cruzbit peer couldn\'t store transaction, PDOException: ' . $e->getMessage() . "\n";
        error_log('cruzbit peer couldn\'t store transaction, PDOException: ' . $e->getMessage());
    }
}

function linkTransaction($height, $block_id, $transaction_id) {
    global $pdo, $settings;
    $query = $pdo->prepare('
        INSERT INTO `block_transactions`
            (`height`, `block_id`, `transaction_id`)
        VALUES
	        (?, ?, ?)
	    ON DUPLICATE KEY UPDATE height = height
    ');
    try {
        $success = $query->execute([
            $height,
            $block_id,
            $transaction_id
        ]);
        if ($settings['verbose']) echo 'Link transaction ' . (($success) ? ' successful.' : ' failed.') . "\n";
    } catch (\PDOException $e) {
        if (!$settings['silent']) echo 'cruzbit peer couldn\'t link transaction, PDOException: ' . $e->getMessage() . "\n";
        error_log('cruzbit peer couldn\'t link transaction, PDOException: ' . $e->getMessage());
    }
}

/**
 * Returns an array of the block_ids from the input array that do not exist in the database
 *
 * @param string[] $block_ids
 * @return string[]
 */
function findUnseenBlocks($block_ids) {
    global $pdo, $settings;
    $query = $pdo->prepare('
        SELECT `block_id` FROM `blocks`
        WHERE `block_id` IN (?)
    ');
    $seen = [];
    try {
        $query->execute([
            '"' . implode('","', $block_ids) . '"'
        ]);
        $found = $query->fetchAll();
        foreach ($found as $block) {
            $seen[] = $block['block_id'];
        }
    } catch (\PDOException $e) {
        if (!$settings['silent']) echo 'cruzbit peer couldn\'t search for block_ids, PDOException: ' . $e->getMessage() . "\n";
        error_log('cruzbit peer couldn\'t search for block_ids, PDOException: ' . $e->getMessage());
    }
    $ret = array_diff($block_ids, $seen);
    if ($settings['verbose']) echo 'Unseen blocks found: ' . count($ret) . "\n";
    return $ret;
}

/**
 * Returns an array of block_id of the most recent blocks in the database (tip)
 *
 * @return string[]
 */
function getLocalTip($limit = 1) {
    global $pdo, $settings;
    try {
        $found = $pdo->query('
            SELECT `block_id` FROM `blocks`
            ORDER BY `height` DESC
            LIMIT ' . $limit
        )->fetchAll();

        $tip = [];
        foreach ($found as $block) {
            $tip[] = $block['block_id'];
        }

        if (count($tip)) {
            if ($settings['verbose']) echo 'Local tip returned id: ' . $tip[0] . "\n";
            return $tip;
        } else {
            if ($settings['verbose']) echo "No local blocks found; setting local tip to genesis block.\n";
            return [GENESIS_BLOCK];
        }
    } catch (\PDOException $e) {
        if (!$settings['silent']) echo 'cruzbit peer couldn\'t find the tip, PDOException: ' . $e->getMessage() . "\n";
        error_log('cruzbit peer couldn\'t find the tip, PDOException: ' . $e->getMessage());
    }
}

/**
 * Determines whether there are missing blocks between tip (last) and tail (first)
 * @return bool
 */
function isFullChain() {
    //An explanation of the math here:
    // - sum = the literal sum of all block heights in the db
    // - gauss = n(n+1)/2, represents the sum of all numbers from 1 to n
    // if (sum != gauss), there are blocks missing, ie:
    //  - 1+2+3+4+5+6+7+8+9=45, or 9(9+1)/2=45
    //  - 1+2+3+4+5+      9=24, and (24 != 45)
    global $pdo;
    $gauss = $pdo->query('
        SELECT h.height AS height, sum(b.height) AS sum, ((h.height * (h.height+1))/2) AS gauss
        FROM blocks AS b
        JOIN (SELECT height FROM blocks ORDER BY height DESC LIMIT 1) AS h;
    ');
    return $gauss['sum'] === $gauss['gauss'];
}
