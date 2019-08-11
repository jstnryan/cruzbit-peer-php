<?php

namespace cruzbit;

/**
 * Class Queue is a FIFO queue of unique values
 */
class Queue {

    private $queue = [];

    public function __construct($data = null) {
        if ($data) {
            $this->addData($data);
        }
    }

    /**
     * Determine if item is already in the queue
     *
     * @param mixed $data
     * @return bool
     */
    public function inQueue($data) {
        return in_array($data, $this->queue, true);
    }

    /**
     * Add one or more items to the end of the queue
     *
     * @param mixed $data
     */
    public function push($data) {
        if (is_array($data)) {
            foreach ($data as $d) {
                $this->addData($d);
            }
        } else {
            $this->addData($data);
        }
        $this->remDupes();
    }

    /**
     * Return the first item in the queue without removing it. Returns false
     *  if there are no items in the queue.
     *
     * @return mixed|false
     */
    public function peek() {
        //return $this->queue[array_key_first($this->queue)];//overkill?
        return reset($this->queue);
    }

    /**
     * Remove and return the item from the top of the queue
     *  - null if empty
     *
     * @return string|null
     */
    public function shift() {
        return array_shift($this->queue);
    }

    /**
     * Return the number of items in the queue
     *
     * @return int
     */
    public function count() {
        return count($this->queue);
    }

    /**
     * Return the items in the queue as an array
     *
     * @return array
     */
    public function toArray() {
        return $this->queue;
    }

    private function addData($data) {
        $this->queue[] = $data;
    }

    private function remDupes() {
        $this->queue = array_values(array_unique($this->queue));
    }

}
