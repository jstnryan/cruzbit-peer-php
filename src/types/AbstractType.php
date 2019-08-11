<?php

namespace cruzbit\types;

class AbstractType {

    public function __construct($data = null) {
        if ($data) {
            $this->hydrateFromArray($data);
        }
    }

    public function toArray() {
        $arr = get_object_vars($this);
        foreach ($arr as $k => $v) {
            if ($v === null) {
                unset($arr[$k]);
            }
        }
        return $arr;
    }

    public function __toString() {
        return json_encode($this->toArray());
    }

    /**
     * Build this object from an array of properties
     *
     * @param array $data
     * @return AbstractType
     */
    public function hydrateFromArray($data) {
        foreach ($data as $key => $val) {
            if (strpos($key, '_') !== false) {
                $e = explode('_', $key);
                $count = count($e);
                for ($i = 1; $i < $count; $i++) {
                    //skip first 0
                    $e[$i] = ucfirst($e[$i]);
                }
                $key = implode('', $e);
            }

            $method = [$this, 'set' . ucfirst($key)];
            is_callable($method) && call_user_func($method, $val);
        }
        return $this;
    }

}
