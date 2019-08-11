<?php

namespace cruzbit;

use cruzbit\types\BlockHeader;
use cruzbit\types\Transaction;
use PDO;

class Database {

    /** @var PDO */
    protected $pdo;
    /** @var Log */
    protected $log;

    /**
     * Initialize database connection
     *
     * @param string    $host ip/hostname:port
     * @param string    $database
     * @param string    $user
     * @param string    $password
     * @param Log|null  $log
     */
    public function __construct(string $host, string $database, string $user, string $password, Log $log) {
        $this->log = $log;

        $dsn = "mysql:host=$host;dbname=$database";
        $opt = [
            PDO::ATTR_ERRMODE               =>  PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE    =>  PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES      =>  false,
        ];
        try {
            $this->pdo = new PDO($dsn, $user, $password, $opt);
        } catch (\PDOException $e) {
            $this->log->write(0, sprintf("cruzbit peer couldn't connect to DB, PDOException: %s", $e->getMessage()));
            //TODO: better error handling/shutdown procedure
            die;
        }
    }

    /**
     * Add a Block to the database
     *
     * @param string $block_id
     * @param BlockHeader $header
     * @return bool Success/Failure
     */
    public function storeBlock(string $block_id, BlockHeader $header) {
        $query = $this->pdo->prepare('
            INSERT INTO `blocks`
                (`block_id`, `previous`, `hash_list_root`, `time`, `target`, `chain_work`, `nonce`, `height`, `transaction_count`)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE block_id = block_id
        ');
        try {
            $success = $query->execute([
                $block_id,
                $header->getPrevious(),
                $header->getHashListRoot(),
                $header->getTime(),
                $header->getTarget(),
                $header->getChainWork(),
                $header->getNonce(),
                $header->getHeight(),
                $header->getTransactionCount()
            ]);
        } catch (\PDOException $e) {
            $this->log->write(1, sprintf("Error storing block, PDOException: %s", $e->getMessage()));
            return false;
        }
        return true;
    }

    /**
     * Return a block's header information by block_id
     *
     * @param string    $block_id
     * @return bool|mixed
     */
    public function getBlockHeader(string $block_id) {
        $query = $this->pdo->prepare('
            SELECT `previous`, `hash_list_root`, `time`, `target`, `chain_work`, `nonce`, `height`, `transaction_count`
            FROM `blocks`
            WHERE `block_id` = ?
            LIMIT 1
        ');
        try {
            $query->execute([$block_id]);
        } catch (\PDOException $e) {
            $this->log->write(1, sprintf("Error retrieving block header, PDOException: %s", $e->getMessage()));
            return false;
        }
        return $query->fetch();
    }

    /**
     * Return a block's height by block_id
     *   - height (int) if found
     *   - null if not found
     *   - false if error
     *
     * @param string    $block_id
     * @return int|null|false
     */
    public function getBlockHeight(string $block_id) {
        $query = $this->pdo->prepare('
            SELECT `height`
            FROM `blocks`
            WHERE `block_id` = ?
            LIMIT 1
        ');
        try {
            $query->execute([$block_id]);
        } catch (\PDOException $e) {
            $this->log->write(1, sprintf("Error retrieving block header, PDOException: %s", $e->getMessage()));
            return false;
        }
        $row = $query->fetch();
        return $row['height'] ?? null;
    }

    /**
     * Return a block's block_id at a given block height
     *   - block_id (string) if found
     *   - null if not found
     *   - false if error
     *
     * @param int $height
     * @return string|null|false
     */
    public function getBlockIdForHeight(int $height) {
        $query = $this->pdo->prepare('
            SELECT `block_id`
            FROM `blocks`
            WHERE `height` = ?
            LIMIT 1
        ');
        try {
            $query->execute([$height]);
        } catch (\PDOException $e) {
            $this->log->write(1, sprintf("Error retrieving block_id, PDOException: %s", $e->getMessage()));
            return false;
        }
        $row = $query->fetch();
        return empty($row) ? null : $row['block_id'];
    }

    /**
     * Determine if block already exists locally, by BlockID
     *
     * @param string $block_id
     * @return bool
     */
    public function isBlockIdProcessed(string $block_id):bool {
        //This is equiv. to, but faster than:
        // SELECT EXISTS(SELECT 1 FROM `blocks` WHERE `block_id` = ?)
        $query = $this->pdo->prepare('
            SELECT 1 FROM `blocks` WHERE `block_id` = ? LIMIT 1
        ');
        try {
            $query->execute([$block_id]);
            return $query->fetch()[0] ?? false;
        } catch (\PDOException $e) {
            $this->log->write(2, sprintf("cruzbit peer couldn't check for block, PDOException: %s", $e->getMessage()));
            return false;
        }
    }

    /**
     * Determine if block already exists locally, by height
     *
     * @param int $height
     * @return bool
     */
    public function isBlockHeightProcessed(int $height):bool {
        $query = $this->pdo->prepare('
            SELECT 1 FROM `blocks` WHERE `height` = ?
        ');
        try {
            return $query->execute([$height]);
        } catch (\PDOException $e) {
            $this->log->write(2, sprintf("cruzbit peer couldn't check for block, PDOException: %s", $e->getMessage()));
            return false;
        }
    }

    /**
     * Add a Transaction to the database
     *
     * @param string $transaction_id
     * @param Transaction $transaction
     * @return bool Success/Failure
     */
    public function storeTransaction(string $transaction_id, Transaction $transaction):bool {
        $query = $this->pdo->prepare('
            INSERT INTO `transactions`
                (`transaction_id`, `time`, `nonce`, `from`, `to`, `amount`, `fee`, `memo`, `matures`, `expires`, `series`, `signature`)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE transaction_id = transaction_id
        ');
        try {
            $success = $query->execute([
                $transaction_id,
                $transaction->getTime(),
                $transaction->getNonce(),
                $transaction->getFrom(),
                $transaction->getTo(),
                $transaction->getAmount(),
                $transaction->getFee(),
                $transaction->getMemo(),
                $transaction->getMatures(),
                $transaction->getExpires(),
                $transaction->getSeries(),
                $transaction->getSignature()
            ]);
        } catch (\PDOException $e) {
            $this->log->write(1, sprintf("cruzbit peer couldn't store transaction, PDOException: %s", $e->getMessage()));
            return false;
        }
        return true;
    }

    /**
     * Associate a Transaction to a Block via join table
     *
     * @param int $height
     * @param string $block_id
     * @param string $transaction_id
     * @return bool Success/Failure
     */
    public function linkTransaction(int $height, string $block_id, string $transaction_id):bool {
        $query = $this->pdo->prepare('
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
        } catch (\PDOException $e) {
            $this->log->write(2, sprintf("cruzbit peer couldn't link transaction, PDOException: %s", $e->getMessage()));
            return false;
        }
        return true;
    }

    /**
     * Returns an array of the block_ids from the input array that do not exist in the database
     *
     * @param string[] $block_ids
     * @return string[]|false
     */
    public function findUnseenBlocks(array $block_ids) {
        $query = $this->pdo->prepare('
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
            $this->log->write(2, sprintf("cruzbit peer couldn't search for block_ids, PDOException: %s", $e->getMessage()));
            return false;
        }
        return array_diff($block_ids, $seen);
    }

    /**
     * Returns an array of block_id of the most recent blocks in the database (tip)
     *
     * @param int  $limit
     * @param bool $ignoreGaps Returns the largest height, regardless of missing blocks
     * @return string[]|string|false
     */
    public function getLocalTip(int $limit = 1, bool $ignoreGaps = true) {
        if ($ignoreGaps === false) {
            return $this->getBlockIdForHeight($this->getMissingBlocks(1)[0]);
        }

        try {
            $found = $this->pdo->query('
                SELECT `block_id` FROM `blocks`
                ORDER BY `height` DESC
                LIMIT ' . $limit
            )->fetchAll();

            $tip = [];
            foreach ($found as $block) {
                $tip[] = $block['block_id'];
            }
            $this->log->write(4, sprintf("getLocalTip: found %d block(s)", count($tip)));

            switch (count($tip)) {
                case 0:
                    return GENESIS_BLOCK;
                    break;
                case 1:
                    return $tip[0];
                    break;
                default:
                    return $tip;
            }
        } catch (\PDOException $e) {
            $this->log->write(2, sprintf("cruzbit peer couldn't find the tip, PDOException: %s", $e->getMessage()));
            return false;
        }
    }

    /**
     * Determines whether there are missing blocks between tip (last) and tail (first)
     *
     * @return bool
     */
    public function isFullChain():bool {
        //An explanation of the math here:
        // - sum = the literal sum of all block heights in the db
        // - gauss = n(n+1)/2, represents the sum of all numbers from 1 to n
        // if (sum != gauss), there are blocks missing, ie:
        //  - 1+2+3+4+5+6+7+8+9=45, or 9(9+1)/2=45
        //  - 1+2+3+4+5+      9=24, and (24 != 45)
        $gauss = $this->pdo->query('
            SELECT h.height AS height, sum(b.height) AS sum, ((h.height * (h.height+1))/2) AS gauss
            FROM blocks AS b
            JOIN (SELECT height FROM blocks ORDER BY height DESC LIMIT 1) AS h;
        ');
        return $gauss['sum'] === $gauss['gauss'];
    }

    /**
     * Returns the block heights of any blocks currently missing from the chain
     *  below the height of the current **local** tip
     *
     * @param null|int $limit
     * @return int[]
     */
    public function getMissingBlocks($limit = null):array {
        //MariaDB ONLY!
        // https://mariadb.com/kb/en/library/sequence-storage-engine/
        $missing = $this->pdo->query('
            SELECT * FROM seq_0_to_999999
            WHERE seq NOT IN (SELECT `height` FROM `blocks`)
            AND seq < (SELECT MAX(`height`) FROM `blocks`)' .
            ($limit !== null ? ' LIMIT ' . (int)$limit : '')
        )->fetchAll();
        return array_map(function($v){return $v['seq'];}, $missing);
    }

}
