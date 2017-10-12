<?php

namespace Serversidebim\IFCReader;

trait IFCEventTrait {
    
    private $eventBus;
    
    function on($name, $callback) {
        
        if (!$this->eventBus) {
            $this->eventBus = new IFCEventBus();
        }
        
        $this->eventBus->registerEvent($name,$callback);
        return $this;
    }
    
    function fireEvent($name, IFCEvent $event) {
        if ($this->eventBus) {
            $this->eventBus->fireEvent($name, $event);
        }
    }
}