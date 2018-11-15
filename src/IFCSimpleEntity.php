<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Serversidebim\IFCReader;

use Serversidebim\ExpressReader\Reader as Scheme;
use Exception;

/**
 * Description of IFCSimpleEntity
 *
 * @author Veem5
 */
class IFCSimpleEntity
{
    //put your code here

    public $class;
    public $data;
    public $id;
    public $raw;

    public function __construct($class, $data, $id = null, $raw = null)
    {
        $this->class = $class;
        $this->data = $data;
        $this->id = $id;
        $this->raw = $raw;
    }

    public function mapToScheme(Scheme $scheme)
    {
        // find the class in the scheme
        // retrieve the full layout of the entity
        $item = $scheme->getFullEntity($this->class);

        // check?
        if (count($this->data) !== count($item->parameters)) {
            throw new Exception("There seems to be a mismatch between the entities on class ".$this->class.": ".count($this->data).' vs '.count($item->parameters));
        }

        $keys = array_keys($item->parameters);

        // set the data of $this to the correct parameters
        $newdata = [];
        for ($i = 0;$i<count($this->data);$i++) {
            $newdata[$keys[$i]] = $this->data[$i];
        }
        $this->data = $newdata;

        return $this;
    }

    public function cleanData(Scheme $scheme)
    {
        $entity = $scheme->getFullEntity($this->class);
        $parameters = $entity->parameters;

        $cleanData = $this->data;

        foreach ($parameters as $paramName=>$param) {
            // Check if type is an entity
            if ($scheme->getEntity($param->type)) {
                // Do nothing
                continue;
            }
            $trueType = $scheme->getType($param->type)->getTrueType();

            $cleanData[$paramName] = $this->cleanParameter($cleanData[$paramName], $trueType, $scheme);
        }

        return $cleanData;
    }

    private function cleanParameter($param, $trueType, Scheme $scheme)
    {
        if ($trueType == 'REAL') {
            return (real)$param;
        }
        if ($trueType == 'NUMBER') {
            return (real)$param;
        }
        if ($trueType == 'INTEGER') {
            return (integer)$param;
        }
        if ($trueType == 'BOOLEAN') {
            $matches = [];
            if (preg_match('/(IFCBOOLEAN\()*\.*([TF])\.*(\))*/', $param, $matches)) {
                return $matches[2] == "T" ? true : false;
            }
        }
        if ($trueType == 'LOGICAL') {
            $matches = [];
            if (preg_match('/(IFCLOGICAL\()*\.*([TFU])\.*(\))*/', $param, $matches)) {
                if ($matches[2] == 'T') {
                    return true;
                }
                if ($matches[2] == 'F') {
                    return true;
                }
                if ($matches[2] == 'U') {
                    return null;
                }
            }
        }
        if ($trueType == 'ENUMERATION') {
            $matches = [];
            if (preg_match('/^\.*(.*?)\.*$/', $param, $matches)) {
                return $matches[1];
            }
        }
        if ($trueType == 'SELECT') {
            $matches = [];
            if (preg_match('/^((IFC.*?)\()*(.*?)(\)*)$/', $param, $matches)) {
                // Check the type
                if ($matches[2]) {
                    $type = $scheme->getType($matches[2])->getTrueType();

                    return [
                        "type"=>$matches[2],
                        "value"=>$this->cleanParameter($matches[3], $type, $scheme)
                      ];
                }
                return $matches[3];
            }
        }
        if ($trueType == "STRING") {
            if (strlen($param) > 1) {
                $firstChar = $param[0];
                $lastChar = $param[strlen($param)-1];
                if (in_array($firstChar, ['"','\'']) && $firstChar === $lastChar) {
                    // remove the quotes
                    return \substr($param, 1, -1);
                }
            }
            return $param;
        }

        return $param;
    }
}
