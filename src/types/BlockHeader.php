<?php

namespace cruzbit\types;

class BlockHeader extends AbstractType {

    /** @var string */
    protected $previous;
    /** @var string */
    protected $hash_list_root;
    /** @var int */
    protected $time;
    /** @var string */
    protected $target;
    /** @var string */
    protected $chain_work;
    /** @var int */
    protected $nonce;
    /** @var int */
    protected $height;
    /** @var int */
    protected $transaction_count;

    /**
     * @return string
     */
    public function getPrevious() {
        return $this->previous;
    }

    /**
     * @param string $previous
     */
    public function setPrevious($previous) {
        $this->previous = $previous;
    }

    /**
     * @return string
     */
    public function getHashListRoot() {
        return $this->hash_list_root;
    }

    /**
     * @param string $hash_list_root
     */
    public function setHashListRoot($hash_list_root) {
        $this->hash_list_root = $hash_list_root;
    }

    /**
     * @return int
     */
    public function getTime() {
        return $this->time;
    }

    /**
     * @param int $time
     */
    public function setTime($time) {
        $this->time = $time;
    }

    /**
     * @return string
     */
    public function getTarget() {
        return $this->target;
    }

    /**
     * @param string $target
     */
    public function setTarget($target) {
        $this->target = $target;
    }

    /**
     * @return string
     */
    public function getChainWork() {
        return $this->chain_work;
    }

    /**
     * @param string $chain_work
     */
    public function setChainWork($chain_work) {
        $this->chain_work = $chain_work;
    }

    /**
     * @return int
     */
    public function getNonce() {
        return $this->nonce;
    }

    /**
     * @param int $nonce
     */
    public function setNonce($nonce) {
        $this->nonce = $nonce;
    }

    /**
     * @return int
     */
    public function getHeight() {
        return $this->height;
    }

    /**
     * @param int $height
     */
    public function setHeight($height) {
        $this->height = $height;
    }

    /**
     * @return int
     */
    public function getTransactionCount() {
        return $this->transaction_count;
    }

    /**
     * @param int $transaction_count
     */
    public function setTransactionCount($transaction_count) {
        $this->transaction_count = $transaction_count;
    }

}
