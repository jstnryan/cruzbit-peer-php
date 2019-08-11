<?php

namespace cruzbit\messages;

class PeerAddressesMessage extends MessageInterface {

    protected $addresses = [];

    /**
     * @return array
     */
    public function getAddresses() {
        return $this->addresses;
    }

    /**
     * @param array $addresses
     */
    public function setAddresses($addresses) {
        $this->addresses = $addresses;
    }

}
