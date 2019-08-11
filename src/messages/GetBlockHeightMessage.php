<?php

namespace cruzbit\messages;

class GetBlockHeightMessage extends MessageInterface {

    const TYPE = 'get_Block_height';

    /** @var int */
    protected $height;

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

}
