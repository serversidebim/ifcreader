<?php

namespace Serversidebim\IFCReader;

use Exception;
use Serversidebim\ExpressReader\Reader as Scheme;
use function substr;

/**
 * Description of IFCSimpleEntity
 *
 * @author Veem5
 */
class IFCSimpleEntity
{

    public string $class;
    public array $data;
    public ?string $id;
    public ?string $raw;

    protected array $baseTypes = [
        'BOOLEAN',
        'REAL',
        'BINARY',
        'INTEGER',
        'NUMBER',
        'STRING',
        'ENUMERATION',
        'SELECT',
        'LOGICAL',
    ];

    public function __construct($class, $data, $id = null, $raw = null)
    {
        $this->class = $class;
        $this->data = $data;
        $this->id = $id;
        $this->raw = $raw;
    }

    /**
     * @throws Exception
     */
    public function mapToScheme(Scheme $scheme): IFCSimpleEntity
    {
        // find the class in the scheme
        // retrieve the full layout of the entity
        $item = $scheme->getFullEntity($this->class);

        // check?
        if (count($this->data) !== count($item->parameters)) {
            throw new Exception("There seems to be a mismatch between the entities on class " . $this->class . ": " . count($this->data) . ' vs ' . count($item->parameters) . '(' . json_encode($this) . ')');
        }

        $keys = array_keys($item->parameters);

        // set the data of $this to the correct parameters
        $newData = [];
        for ($i = 0; $i < count($this->data); $i++) {
            $newData[$keys[$i]] = $this->data[$i];
        }
        $this->data = $newData;

        return $this;
    }

    public function cleanData(Scheme $scheme): array
    {
        $entity = $scheme->getFullEntity($this->class);
        $parameters = $entity->parameters;

        $cleanData = $this->data;

        foreach ($parameters as $paramName => $param) {
            // Check if type is an entity

            $type = is_array($param->type) ? $param->type['OF'] : $param->type;

            if ($scheme->getEntity($type)) {
                // Do nothing
                continue;
            }

            if (in_array($type, $this->baseTypes)) {
                $trueType = $type;
            } else {
                $trueType = $scheme->getType($type)->getTrueType();
            }

            $cleanData[$paramName] = $this->cleanParameter($cleanData[$paramName], $trueType, $scheme);
        }

        return $cleanData;
    }

    private function cleanParameter($param, $trueType, Scheme $scheme)
    {
        if (is_array($param)) {
            for ($i = 0; $i < count($param); $i++) {
                $param[$i] = $this->cleanParameter($param[$i], $trueType, $scheme);
            }
            return $param;
        }

        if ($param == '*') {
            return $param;
        }
        if ($param == '$') {
            return $param;
        }

        if ($trueType == 'REAL') {
            return (float)$param;
        }
        if ($trueType == 'NUMBER') {
            return (float)$param;
        }
        if ($trueType == 'INTEGER') {
            return (integer)$param;
        }
        if ($trueType == 'BOOLEAN') {
            $matches = [];
            if (preg_match('/(IFCBOOLEAN\()*\.*([TF])\.*(\))*/', $param, $matches)) {
                return $matches[2] == "T";
            }
        }
        if ($trueType == 'LOGICAL') {
            $matches = [];
            if (preg_match('/(IFCLOGICAL\()*\.*([TFU])\.*(\))*/', $param, $matches)) {
                if ($matches[2] == 'T') {
                    return true;
                }
                if ($matches[2] == 'F') {
                    return false;
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

                    $returnAr = [
                        "type" => $matches[2],
                        "value" => $this->cleanParameter($matches[3], $type, $scheme)
                    ];
                    if ($type == "REAL" || $type == "NUMBER") {
                        $returnAr['orig_value'] = $matches[3];
                    }
                    return $returnAr;
                }
                return $matches[3];
            }
        }
        if ($trueType == "STRING") {
            if (strlen($param) > 1) {
                $firstChar = $param[0];
                $lastChar = $param[strlen($param) - 1];
                if (in_array($firstChar, ['"', '\'']) && $firstChar === $lastChar) {
                    // remove the quotes
                    return substr($param, 1, -1);
                }
            }
            return $param;
        }

        return $param;
    }
}
