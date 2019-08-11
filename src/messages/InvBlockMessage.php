<?php

namespace cruzbit\messages;

class InvBlockMessage extends MessageInterface {

    const TYPE = 'inv_block';

    /** @var string[] */
    protected $block_ids = [];

    /**
     * @return string[]
     */
    public function getBlockIds() {
        return $this->block_ids;
    }

    /**
     * @param string[] $block_ids
     */
    public function setBlockIds($block_ids) {
        $this->block_ids = $block_ids;
    }

    /**
     * @param string $block_id
     */
    public function addBlockId($block_id) {
        $this->block_ids[] = $block_id;
    }

}
