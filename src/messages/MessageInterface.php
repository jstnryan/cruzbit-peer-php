<?php

namespace cruzbit\messages;

use cruzbit\types\AbstractType;

abstract class MessageInterface extends AbstractType {

    const TYPE = 'message_type';

    public function getType() {
        return $this::TYPE;
    }

}
