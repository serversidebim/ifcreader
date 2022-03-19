<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Serversidebim\IFCReader;

use Exception;

/**
 * Description of IFCFileHeader
 *
 * @author Veem5
 * @property object FILE_DESCRIPTION
 * @property object FILE_NAME
 * @property object FILE_SCHEMA
 * @property object FILE_POPULATION
 * @property object SECTION_LANGUAGE
 * @property object SECTION_CONTEXT
 */
class IFCFileHeader
{

    private array $headerlist = [
        "FILE_DESCRIPTION" => ["description", "implementation_level"],
        "FILE_NAME" => ["name", "time_stamp", "author", "organization", "preprocessor_version", "originating_system", "authorization"],
        "FILE_SCHEMA" => ["schema"],
        "FILE_POPULATION" => ["governing_schema", "determination_method", "governed_sections"],
        "SECTION_LANGUAGE" => ["language"],
        "SECTION_CONTEXT" => ["context"],
    ];

    private array $items = [];

    public function __construct()
    {
        // create the header items
        foreach ($this->headerlist as $key => $value) {
            $item = new IFCFileHeaderItem($key, $value);
            $this->items[$key] = $item;
        }
    }

    /**
     * @throws Exception
     */
    public function __get($name)
    {
        if (key_exists($name, $this->items)) {
            return $this->items[$name];
        } else {
            throw new Exception("$name does not exist in IFCFileHeader");
        }
    }

    public function setByArray($name, $array): IFCFileHeader
    {
        $item = $this->$name;
        $headerdata = $this->headerlist[$name];
        for ($i = 0; $i < count($headerdata) && $i < count($array); $i++) {

            $item->{$headerdata[$i]} = $array[$i];
        }
        return $this;
    }

}
