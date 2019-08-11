<?php

namespace cruzbit\messages;

class FindCommonAncestorMessage extends MessageInterface {

    const TYPE = 'find_common_ancestor';

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

}
