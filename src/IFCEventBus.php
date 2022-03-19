<?php

namespace Serversidebim\IFCReader;

class IFCEventBus
{
    private array $callbacks = [];

    public function registerEvent($name, $callback)
    {
        if (!isset($this->callbacks[$name])) {
            $this->callbacks[$name] = [];
        }

        $this->callbacks[$name][] = $callback;

    }

    public function unregisterEvent($name, $callback)
    {

    }

    public function fireEvent($name, IFCEvent $event)
    {
        if (isset($this->callbacks[$name])) {
            foreach ($this->callbacks[$name] as $callback) {
                call_user_func($callback, $event);
            }
        }
    }
}