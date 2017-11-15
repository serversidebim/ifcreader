<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Serversidebim\IFCReader;

use Serversidebim\ExpressReader\Reader as Scheme;

/**
 * Description of IFCSimpleEntity
 *
 * @author Veem5
 */
class IFCSimpleEntity {
    //put your code here
    
    public $class;
    public $data;
    public $id;
    public $raw;
    
    function __construct($class, $data, $id = null, $raw = null) {
        $this->class = $class;
        $this->data = $data;
        $this->id = $id;
        $this->raw = $raw;
    }
    
    public function mapToScheme(Scheme $scheme) {
        // find the class in the scheme
        // retrieve the full layout of the entity
        $item = $scheme->getFullEntity($this->class);
        
        // check?
        if (count($this->data) !== count($item->parameters)){
            throw new Exception("There seems to be a mismatch between the entities");
        }
        
        $keys = array_keys($item->parameters);
        
        // set the data of $this to the correct parameters
        $newdata = [];
        for($i = 0;$i<count($this->data);$i++) {
            $newdata[$keys[$i]] = $this->data[$i];
        }
        $this->data = $newdata;
        
        return $this;
    }
}
