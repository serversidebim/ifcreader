<?php

namespace Serversidebim\IFCReader;

class IFCEvent {
    public $data;
    
    function __construct($data = null) {
        $this->data = $data;
    }
}