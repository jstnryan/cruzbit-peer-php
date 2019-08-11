<?php

namespace cruzbit\messages;

class GetBlockMessage extends MessageInterface {

    const TYPE = 'get_block';

    /** @var string */
    protected $block_id;

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

}
