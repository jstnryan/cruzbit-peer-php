<?php

namespace cruzbit\types;

class Transaction extends AbstractType {

    /** @var int */
    protected $time;
    /** @var int */
    protected $nonce;
    /** @var null|string */
    protected $from = null;
    /** @var string */
    protected $to;
    /** @var int */
    protected $amount;
    /** @var null|int */
    protected $fee = null;
    /** @var null|string */
    protected $memo = null;
    /** @var null|int */
    protected $matures = null;
    /** @var null|int */
    protected $expires = null;
    /** @var int */
    protected $series;
    /** @var null|string */
    protected $signature = null;

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
     * @return string|null
     */
    public function getFrom() {
        return $this->from;
    }

    /**
     * @param string|null $from
     */
    public function setFrom($from) {
        $this->from = $from;
    }

    /**
     * @return string
     */
    public function getTo() {
        return $this->to;
    }

    /**
     * @param string $to
     */
    public function setTo($to) {
        $this->to = $to;
    }

    /**
     * @return int
     */
    public function getAmount() {
        return $this->amount;
    }

    /**
     * @param int $amount
     */
    public function setAmount($amount) {
        $this->amount = $amount;
    }

    /**
     * @return int|null
     */
    public function getFee() {
        return $this->fee;
    }

    /**
     * @param int|null $fee
     */
    public function setFee($fee) {
        $this->fee = $fee;
    }

    /**
     * @return string|null
     */
    public function getMemo() {
        return $this->memo;
    }

    /**
     * @param string|null $memo
     */
    public function setMemo($memo) {
        $this->memo = $memo;
    }

    /**
     * @return int|null
     */
    public function getMatures() {
        return $this->matures;
    }

    /**
     * @param int|null $matures
     */
    public function setMatures($matures) {
        $this->matures = $matures;
    }

    /**
     * @return int|null
     */
    public function getExpires() {
        return $this->expires;
    }

    /**
     * @param int|null $expires
     */
    public function setExpires($expires) {
        $this->expires = $expires;
    }

    /**
     * @return int
     */
    public function getSeries() {
        return $this->series;
    }

    /**
     * @param int $series
     */
    public function setSeries($series) {
        $this->series = $series;
    }

    /**
     * @return string|null
     */
    public function getSignature() {
        return $this->signature;
    }

    /**
     * @param string|null $signature
     */
    public function setSignature($signature) {
        $this->signature = $signature;
    }

}
