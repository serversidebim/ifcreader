<?php

namespace Serversidebim\IFCReader;

use Exception;

/**
 *  IFCStepReader
 *
 * Class to read, index and parse the contents of an IFC file provided
 * in STEP format.
 *
 *  @author Maarten Veerman
 */
class IFCStepReader extends IFCBaseReader
{
    use IFCEventTrait;

    private $filename = null;
    private $fh = null;
    public $header;
    private $headerstart = null;
    private $headerend = null;
    private $feof = false;
    private $indexed = false;
    private $indexfile = null;
    private $index = null;
    private $loadedFromFile = false;

    private $offset = 0;
    private $maxLines = PHP_INT_MAX;
    private $maxPointer = PHP_INT_MAX;
    private $pointer = 0;
    private $previousPointer = 0;


    public function __construct($filename = null)
    {
        $this->header = new IFCStepFileHeader();
        $this->filename = $filename;
    }

    public function __destruct()
    {
        if (is_resource($this->fh)) {
            fclose($this->fh);
        }
        if ($this->index) {
            $this->index->close();
            @unlink($this->indexfile);
        }
    }

    public function loadFromFile(string $filename)
    {
        $this->closeFile();
        $this->filename = $filename;
        $this->openFile();
        $this->load();
    }

    public function loadFromResource($handle)
    {
        $this->filename = null;
        if (!is_resource($handle)) {
            throw new Exception("Provided resource is not a valid resource", 1);
        }
        $this->fh = $handle;
        $this->load();
    }

    public function load()
    {
        // get the file handle
        $fh = $this->openFile();

        // Now that we have opened the file, check it
        $this->checkFile($fh);

        // Parse the header
        $this->parseHeader($fh);
    }

    private function checkFile($fh)
    {
        rewind($fh);
        while ($line = fgets($fh)) {
            $line = trim($line);
            if ($line == "ISO-10303-21;") { // TODO: Sample IFC files include extra quotes?
                // OK!
                break;
            } elseif ($line[0] == "/") {
                // it's a comment....
                // TODO: while through file until comment over?
            } else {
                throw new Exception("Specified file does not seem to be a STEP file (ISO-10303-21)");
            }
        }

        rewind($fh);

        return;
    }

    private function parseHeader($fh)
    {
        $header = $this->findHeader($fh);
        $this->header->parseRaw($header);
    }

    public function schema()
    {
        return $this->header->FILE_SCHEMA->schema[0];
    }

    private function findHeader($fh)
    {
        $header = "";
        $found = false;
        $foundEnd = false;
        rewind($fh);

        $cursor = ftell($fh);
        while ($line = fgets($fh)) {
            if (!$found) {
                // look for HEADER
                if (preg_match('@^HEADER;@i', trim($line))) {
                    // header found
                    $found = true;
                    $this->headerstart = $cursor;
                    $header .= $line;
                }
                $cursor = ftell($fh);
            } else {
                // look for ENDSEC
                $header .= $line;
                if (trim($line) == "ENDSEC;") {
                    $foundEnd = true;
                    $this->headerend = ftell($fh);
                    break;
                }
            }
        }

        if ($foundEnd) {
            return $header;
        } else {
            throw new Exception("Could not find header in file " . $this->filename);
        }
    }

    public function parse()
    {
        if ($this->feof) {
            return; // file already parsed
        }
        $fh = $this->openFile();
        if ($this->headerend) {
            fseek($fh, $this->headerend);
        }

        while (!feof($fh)) {
            $linedata = $this->parseNextLine();
            if ($linedata && $linedata['id']) {
                // found an IFC record
                $entity = $this->createEntityFromArray($linedata);
                $this->fireEvent('entity', new IFCEvent($entity));
            }
        }

        $this->feof = true; // file fully parsed
    }

    public function createEntityFromArray($data)
    {
        $entity = new IFCSimpleEntity($data['class'], $data['data'], $data['id'], $data['raw']);
        return $entity;
    }

    public function openFile()
    {
        if (!is_resource($this->fh)) {
            if (is_file($this->filename)) {
                $fh = fopen($this->filename, "r");
                if (!is_resource($fh)) {
                    throw new Exception("Could not open file $this->filename");
                }
                $this->loadedFromFile = true;
            } else {
                throw new Exception("Filename $this->filename is not a file");
            }
            $this->fh = $fh;
            return $fh;
        } else {
            return $this->fh;
        }
    }

    public function closeFile()
    {
        if (is_resource($this->fh) && $this->loadedFromFile) {
            return fclose($this->fh);
        }
        return true;
    }

    /**
     * Retrieve the next full parseble line from the file
     * Cleans the line from comments and checks if line
     * spans multiple lines in the file
     * @return string The next line extracted from the file
     */
    private function getNextLine()
    {
        $fh = $this->fh;
        $line = fgets($fh);

        if ($line === false) {
            return false;
        }

        $line = $this->cleanLine($line);

        // line exists, check if it ends with a ;
        if (!preg_match('/;\s*$/s', $line)) {
            // line does not end with a ;, so append the next line
            $line = preg_replace('/\r?\n/m', '', $line); // replace newline chars
            $line .= $this->getNextLine();
        }

        return $line;
    }

    /**
     * Strips comments from line and applies trim()
     * @param string $line The line to clean
     * @return string The cleaned version of $line
     */
    private function cleanLine($line)
    {
        return trim(preg_replace('/\/\*.*?\*\/\s*/', '', $line));
    }

    private function parseNextLine()
    {
        $line = $this->getNextLine();
        if ($line === false) {
            return false;
        }
        return self::parseLineForData($line);
    }

    public static function parseLineForData($line)
    {
        // first we catch the name
        $matches = [];
        if (preg_match('/^(\#(\d+)\s?=\s?)?(\w+\b)+(.*?)$/', $line, $matches) == 1) {
            // match found
            $id = $matches[2];
            $name = $matches[3];
            $raw = $matches[4];

            $items = array();
            $in_item = false;
            $in_quotes = false;
            $parseUntilNextComma = false;
            $quote = "";
            $value = null;
            $escaped = false;

            $arrays = [&$items];

            //for ($i = strpos($line, $name) + strlen($name); $i < strlen($line); $i++) {
            for ($i = 0;$i<strlen($raw);$i++) {
                $char = $raw[$i];

                // parseUntilNextComma is meant for situations like:
                // #1112= IFCPROPERTYSINGLEVALUE('Type Name',$,IFCTEXT('Level 1'),$);
                // where there is suddenly a reference to an IFC type (IFCTEXT) in this case

                if ($in_quotes && $char == $quote && $raw[$i+1] == $quote && !$escaped) {
                  // Escaping!
                  $escaped = true;
                  continue;
                } elseif ($parseUntilNextComma && $in_quotes && $char == $quote && !$escaped) {
                    // ending quote
                    $in_quotes = false;
                    $quote = "";
                    $value .= $char;
                } elseif ($parseUntilNextComma && !$in_quotes && ($char == '\'' || $char == '\"')) { // || $char == "(")
                    $in_quotes = true;
                    /*if ($char == "(") {
                        $quote = ")";
                    } else {*/
                    $quote = $char;
                    //}
                    $value .= $char;
                } elseif ($parseUntilNextComma && $in_quotes) { // if in quotes, just add to value
                    $value .= $char;
                } elseif ($parseUntilNextComma && strtoupper($value[0]) == "I" && $char == ")") {
                    $value .= $char;
                    $parseUntilNextComma = false;
                } elseif ($parseUntilNextComma && $char != ',' && $char != ")") { // not in quotes, not a comma
                    $value .= $char;
                } elseif (($char=='.' || $char=='I') && !$in_quotes && !isset($value)) {
                    $value = $char;
                    $in_quotes = false;
                    $parseUntilNextComma = true;
                } elseif ($char == "(" && !$in_item) {
                    // entering item
                    $in_item = true;
                } elseif ($char == "(" && !$in_quotes) {
                    // start of new array?
                    if (isset($ar)) {
                        unset($ar);
                    }
                    $ar = [];
                    $arrays[] = &$ar;
                } elseif ($char == ")" && !$in_quotes) {
                    // ending of array or item
                    if (count($arrays) == 1) {
                        // closing of item
                        if (isset($value)) {
                            $end_ar = &$arrays[count($arrays) - 1];
                            //add value to array
                            array_push($end_ar, $value);
                            unset($value);
                        }
                        break;
                    } else {
                        // closing of array
                        if (isset($value)) {
                            $end_ar = &$arrays[count($arrays) - 1];
                            //add value to array
                            array_push($end_ar, $value);
                            unset($value);
                        }

                        $ar = &$arrays[count($arrays) - 1];
                        array_pop($arrays);
                        $end_ar = &$arrays[count($arrays) - 1];
                        $end_ar[] = & $ar;
                    }
                } elseif ($char == "," && !$in_quotes) {
                    // seperator character
                    if (isset($value)) {
                        //add value to array
                        $end_ar = &$arrays[count($arrays) - 1];
                        array_push($end_ar, $value);
                        unset($value);
                    }
                    $parseUntilNextComma = false;
                } elseif (in_array($char, ["\"", "'"])) { // quote
                    if ($in_quotes) {
                        // check if quote is equal
                        if ($char == $quote && !$escaped) {
                            // ending of quote
                            $in_quotes = false;
                            //add value to array
                            $end_ar = &$arrays[count($arrays) - 1];
                            array_push($end_ar, $value);
                            unset($value);
                        } else {
                            // quote not equal or escaped, add to value
                            $value .= $char;
                        }
                    } else { // not in quotes, start quote
                        $in_quotes = true;
                        $value = "";
                        $quote = $char;
                    }
                } else {
                    if (!isset($value)) {
                        $value = "";
                    }
                    $value .= $char;
                }
                $escaped = false;
            }

            return [
                "id" => $id,
                "class" => $name,
                "raw"=> $raw,
                "data" => $items,
            ];
        } else {
            return false; // TODO or throw error?
        }
    }

    /**
     * Loops through the IFC file and fires an event each time line is found
     * @return boolean True on success
     * @throws Exception On failure, in case of non-existence of $this->filename
     */
    public function index()
    {
        $fh = $this->openFile();
        fseek($fh, $this->offset);

        $counter = 0;
        while (!feof($fh) && $counter < $this->maxLines && $this->pointer < $this->maxPointer) {
            $this->previousPointer = $this->pointer;
            $line = $this->cleanLine(fgets($fh));
            $this->pointer = ftell($fh);

            $match = [];
            /*if (preg_match("/^\s*\#(\d+)/", $line, $match)) {
                // index the line!
                $this->fireEvent('index', new IFCEvent(["id"=>$match[1],"line"=>$line]));
                $counter++;
            }*/
            if (preg_match('/^(\#(\d+)\s?=\s?){1}(\w+\b)+(.*?)$/', $line, $match) == 1) {
                // match found
                // index the line!
                $this->fireEvent('index', new IFCEvent([
                  "id"=>$match[2],
                  "name"=>$match[3],
                  "raw"=>$match[4],
                  "line"=>$line
                ]));
                $counter++;
            }
            // continue the while loop
        }

        if (feof($fh)) {
            $this->indexed = true;
        }

        return true;
    }

    public function offset($offset)
    {
        $this->offset = $offset;
        return $this;
    }

    public function maxLines($maxLines)
    {
        $this->maxLines = $maxLines;
        return $this;
    }

    public function maxPointer($maxPointer)
    {
        $this->maxPointer = $maxPointer;
        return $this;
    }

    public function feof()
    {
        return $this->feof;
    }

    public function indexed()
    {
        return $this->indexed;
    }

    public function pointer()
    {
        return $this->pointer;
    }

    public function previousPointer()
    {
        return $this->previousPointer;
    }
}
