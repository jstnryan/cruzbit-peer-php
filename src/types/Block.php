<?php

namespace cruzbit\types;

class Block extends AbstractType {

    /** @var BlockHeader */
    protected $header;
    /** @var Transaction[] */
    protected $transactions = [];

    /**
     * @return BlockHeader
     */
    public function getHeader() {
        return $this->header;
    }

    /**
     * @param BlockHeader $header
     */
    public function setHeader($header) {
        if (is_array($header)) {
            $this->header = new BlockHeader($header);
        } else {
            $this->header = $header;
        }
    }

    /**
     * @return Transaction[]
     */
    public function getTransactions() {
        return $this->transactions;
    }

    /**
     * @param Transaction[] $transactions
     */
    public function setTransactions($transactions) {
        foreach ($transactions as $transaction) {
            if (is_array($transaction)) {
                $this->transactions[] = new Transaction($transaction);
            } else {
                $this->transactions[] = $transaction;
            }
        }
    }

    /**
     * Return the block_id (hash) for this block
     *
     * @return string
     */
    public function getBlockId() {
        return self::generateBlockId($this->header->__toString());
    }

    /**
     * Generate a block_id (hash) for a given header
     *
     * @param string $header
     * @return string
     */
    public static function generateBlockId(string $header) {
        return hash('sha3-256', $header, false);
    }

}
