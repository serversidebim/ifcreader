<?php 

namespace Serversidebim\IFCReader;

interface iIFCReader {
	public function load();
	public function parse();
        public function index($folder);
        public function find($id);
}