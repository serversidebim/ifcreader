<?php

namespace Serversidebim\IFCReader;

trait IFCEventTrait
{

    private ?IFCEventBus $eventBus = null;

    function on($name, $callback)
    {

        if (!$this->eventBus) {
            $this->eventBus = new IFCEventBus();
        }

        $this->eventBus->registerEvent($name, $callback);
        return $this;
    }

    function fireEvent($name, IFCEvent $event)
    {
        $bus = $this->eventBus;
        if (!is_null($bus)) {
            $bus->fireEvent($name, $event);
        }
    }
}