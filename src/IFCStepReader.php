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
class IFCStepReader implements iIFCReader {

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

    public function __construct($filename) {
        $this->header = new IFCStepFileHeader();
        $this->filename = $filename;
    }

    public function __destruct() {
        if (is_resource($this->fh)) {
            fclose($this->fh);
        }
        if ($this->index) {
            $this->index->close();
            @unlink($this->indexfile);
        }
    }

    public function load() {
        $filename = $this->filename;

        if (is_file($filename)) {
            $this->filename = $filename;
            $fh = fopen($filename, "r");
            if (!is_resource($fh)) {
                throw new Exception("Could not open file $filename");
            }

            // Now that we have opened the file, check it
            $this->checkFile($fh);

            // Parse the header
            $this->parseHeader($fh);

            fclose($fh);
        } else {
            throw new Exception("Filename $filename is not a file");
        }
    }

    private function checkFile($fh) {
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

    private function parseHeader($fh) {
        $header = $this->findHeader($fh);
        $this->header->parseRaw($header);
    }

    public function schema() {
        return $this->header->FILE_SCHEMA->schema[0];
    }

    private function findHeader($fh) {
        $header = "";
        $found = false;
        $foundEnd = false;
        rewind($fh);

        $cursor = ftell($fh);
        while ($line = fgets($fh)) {
            if (!$found) {
                // look for HEADER
                if (trim($line) == "HEADER;") {
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

    public function parse() {
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
                //echo $linedata['id'] . "\n";
                $entity = new IFCSimpleEntity($linedata['class'], $linedata['data'], $linedata['id'], $linedata['raw']);
                $this->fireEvent('entity', new IFCEvent($entity));
            }
        }
        
        $this->feof = true; // file fully parsed
        
    }

    public function openFile() {
        if (!is_resource($this->fh)) {
            if (is_file($this->filename)) {
                $fh = fopen($this->filename, "r");
                if (!is_resource($fh)) {
                    throw new Exception("Could not open file $filename");
                }
            } else {
                throw new Exception("Filename $filename is not a file");
            }
            $this->fh = $fh;
            return $fh;
        } else {
            return $this->fh;
        }
    }
    
    /**
     * Retrieve the next full parseble line from the file
     * Cleans the line from comments and checks if line
     * spans multiple lines in the file
     * @return string The next line extracted from the file
     */
    private function getNextLine() {
        $fh = $this->fh;
        $line = fgets($fh);
        
        if ($line === false) {
            return false;
        }
        
        $line = $this->cleanLine($line);
        
        // line exists, check if it ends with a ;
        if (!preg_match('/;\s*$/s', $line)) {
            // line does not end with a ;, so append the next line
            $line = preg_replace('/\r?\n/m','',$line); // replace newline chars
            $line += $this->getNextLine(); 
        }
        
        return $line;
    }
    
    /**
     * Strips comments from line and applies trim()
     * @param string $line The line to clean
     * @return string The cleaned version of $line
     */
    private function cleanLine($line) {
        return trim(preg_replace('/\/\*.*?\*\/\s*/', '', $line));
    }

    private function parseNextLine() {
        $line = $this->getNextLine();
        if ($line === false) {
            return false;
        }
        return self::parseLineForData($line);
    }

    public static function parseLineForData($line) {
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
            $quote = "";
            $value = "";

            $arrays = [&$items];

            //for ($i = strpos($line, $name) + strlen($name); $i < strlen($line); $i++) {
            for ($i = 0;$i<strlen($raw);$i++) {
                $char = $raw[$i];
                if ($char == "(" && !$in_item) {
                    // entering item
                    $in_item = true;
                } elseif ($char == "(" && !$in_quotes) {
                    // start of new array?
                    if (isset($ar)) {
                        unset($ar);
                    }
                    $ar = [];
                    $arrays[] = & $ar;
                } elseif ($char == ")" && !$in_quotes) {
                    // ending of array or item
                    if (count($arrays) == 1) {
                        // closing of item
                        if (isset($value)) {
                            //add value to array
                            $end_ar = &$arrays[count($arrays) - 1];
                            array_push($end_ar, $value);
                            unset($value);
                        }
                        break;
                    } else {
                        // closing of array
                        if (isset($value)) {
                            //add value to array
                            $end_ar = &$arrays[count($arrays) - 1];
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
                } elseif (in_array($char, ["\"", "'"])) { // quote
                    if ($in_quotes) {
                        // check if quote is equal
                        if ($char == $quote && $line[$i - 1] != "\\") {
                            // ending of quote
                            $in_quotes = false;
                            //add value to array
                            $end_ar = &$arrays[count($arrays) - 1];
                            array_push($end_ar, $value);
                            unset($value);
                            //var_dump($end_ar);
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
     * Loops through the IFC file and indexes the id's to a
     * temporaty SQLite database. This database is stored in $folder, or
     * in the sys_get_temp_dir() folder if unspecified by the user.
     * 
     * The temporary SQLite database is removed automatically when this
     * class is unloaded
     * 
     * @param string $folder Path to the folder to store the temporary database
     * @return boolean True on success
     * @throws Exception On failure, in case of non-existence of $this->filename
     */
    public function index($folder = null) {
        $filename = $this->filename;
        
        if ($folder !== null) {
            if ($folder = realpath($folder)) {
                if (!is_dir($folder)) {
                    throw new Exception("Folder $folder is not a folder");
                }
            }
            else {
                throw new Exception("Folder $folder does not exist");
            }
        }
        else {
            $folder = sys_get_temp_dir();
        }

        if (is_file($filename)) {
            $this->filename = $filename;
            $fh = fopen($filename, "r");
            if (!is_resource($fh)) {
                throw new Exception("Could not open file $filename");
            }
            
            $this->indexfile = tempnam($folder, 'IFCReader_');
            $db = new \SQLite3($this->indexfile);
            $db->exec('CREATE TABLE ifc (i STRING, l INTEGER)');
            $this->index = $db;

            // Now that we have opened the file, index it
            //$counter = 0;
            $db->exec("BEGIN TRANSACTION");
            while(!feof($fh)) {
                $num = ftell($fh);
                $line = $this->cleanLine(fgets($fh));
                
                $match = [];
                if (preg_match("/^\s*\#(\d+)/",$line,$match)) {
                    // index the line!

                    $sql = "INSERT INTO ifc (i,l) VALUES ('".$match[1]."', ".$num.")";
                    // TODO: use prepared statement?
                    
                    $db->exec($sql);
                    
                }
                // continue the while loop
            }
            $db->exec("COMMIT");

            fclose($fh);
        } else {
            throw new Exception("Filename $filename is not a file");
        }
        
        $this->indexed = true;
        
        return true;
    }
    
    /**
     * 
     * @param type $id
     * @return type
     */
    public function find($id) {
        if ($this->indexed) {
            $db = $this->index;
            
            $stmt = $db->prepare('SELECT l FROM ifc WHERE i = :id');
            $stmt->bindValue(':id', $id);

            $result = $stmt->execute();

            return $result->fetchArray()[0];
        }
    }

}
