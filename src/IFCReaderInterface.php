<?php

namespace Serversidebim\IFCReader;

interface IFCReaderInterface
{
    public function load();

    public function parse();

    public function index();
}
