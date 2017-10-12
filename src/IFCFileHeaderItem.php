<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Serversidebim\IFCReader;

/**
 * Description of IFCFileHeaderItem
 *
 * @author Veem5
 */
class IFCFileHeaderItem {
    private $ar = [];
    
    private $name = "";
    
    public function __construct($name, $values) {
        $this->name = $name;
        foreach ($values as $key => $value) {
            $this->ar[$value] = null;
        }
    }
    
    public function getName() {
        return $this->name;
    }
    
    public function __get($name) {
        if (key_exists($name, $this->ar)) {
            return $this->ar[$name];
        }
        return null;
    }
    
    public function __set($name, $value) {
        if (key_exists($name, $this->ar)) {
            $this->ar[$name] = $value;
        }
        else {
            throw new Exception("Key $name does not exist in IFCFileHeaderItem");
        }
    }
    
}
