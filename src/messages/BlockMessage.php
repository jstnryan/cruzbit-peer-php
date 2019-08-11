<?php

namespace cruzbit\messages;

use cruzbit\types\Block;

class BlockMessage extends MessageInterface {

    const TYPE = 'block';

    /** @var string */
    protected $block_id;
    /** @var Block */
    protected $block;

    /**
     * @return string
     */
    public function getBlockId() {
        return $this->block_id;
    }

    /**
     * @param string $block_id
     */
    public function setBlockId($block_id) {
        $this->block_id = $block_id;
    }

    /**
     * @return Block
     */
    public function getBlock() {
        return $this->block;
    }

    /**
     * @param Block $block
     */
    public function setBlock($block) {
        $this->block = $block;
    }

}
