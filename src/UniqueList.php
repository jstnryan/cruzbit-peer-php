<?php

namespace cruzbit;

/**
 * Class Queue is a unordered list of unique values
 */
class UniqueList {

    private $list = [];

    public function __construct($data = null) {
        if ($data) {
            $this->addData($data);
        }
    }

    /**
     * Determine if item is already in the list
     *
     * @param mixed $data
     * @return bool
     */
    public function inQueue($data) {
        return in_array($data, $this->list, true);
    }

    /**
     * Add one or more items to the end of the list
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
     * Remove items from the list; accepts a single value, or an array
     *
     * @param mixed $data
     */
    public function pull($data) {
        if (is_array($data)) {
            $this->list = array_diff($this->list, $data);
        } else {
            $k = array_search($data, $this->list, true);
            if ($k !== false) {
                unset($this->list[$k]);
            }
        }
    }

    /**
     * Return the first item in the list without removing it. Returns false
     *  if there are no items in the list.
     *
     * @return mixed|false
     */
    public function peek() {
// PHP >= 7.3.0
//        $pos = array_key_first($this->list);
        $pos = array_keys($this->list)[0];
        if ($pos === null) {
            return false;
        }
        return $this->list[$pos];
    }

    /**
     * Remove and return the item from the top of the queue
     *
     * @return mixed
     */
    public function shift() {
        return array_shift($this->list);
    }

    /**
     * Return the number of items in the queue
     *
     * @return int
     */
    public function count() {
        return count($this->list);
    }

    /**
     * Return the items in the queue as an array
     *
     * @return array
     */
    public function toArray() {
        return $this->list;
    }

    private function addData($data) {
        $this->list[] = $data;
    }

    private function remDupes() {
        $this->list = array_unique($this->list);
    }

}
