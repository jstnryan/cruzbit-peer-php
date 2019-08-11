<?php

namespace cruzbit\messages;

use cruzbit\messages\MessageInterface as MessageInterface;

class Message {

    /** @var string */
    protected $type;
    /** @var MessageInterface */
    protected $body;

    /**
     * @return string
     */
    public function getType() {
        return $this->type;
    }

    /**
     * @return \cruzbit\messages\MessageInterface
     */
    public function getBody() {
        return $this->body;
    }

    /**
     * @param \cruzbit\messages\MessageInterface $body
     */
    public function setBody($body) {
        $this->type = $body->getType();
        $this->body = $body;
    }

    public function __construct($data = null) {
        if ($data) {
            $this->hydrateFromArray($data);
        }
    }

    public function toArray() {
        $arr = get_object_vars($this);
        $arr['body'] = $this->body->toArray();
        return $arr;
    }

    public function __toString() {
        return json_encode($this->toArray());
    }

    /**
     * Build this object from an array of properties
     *
     * @param array $data
     * @return Message
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
