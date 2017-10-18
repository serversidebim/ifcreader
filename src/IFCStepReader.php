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
    private $db = null;

    public function __construct($filename) {
        $this->header = new IFCStepFileHeader();
        $this->filename = $filename;
    }

    public function __destruct() {
        if (is_resource($this->fh)) {
            fclose($this->fh);
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

    public function parse($temp = TRUE, $folder = NULL, $filename = NULL) {
        if ($this->feof) {
            return; // file already parsed
        }
        $fh = $this->openFile();
        if ($this->headerend) {
            fseek($fh, $this->headerend);
        }
        
        $this->db->begin_transaction();
        
        while (!feof($fh)) {
            $linedata = $this->parseNextLine();
            if ($linedata && $linedata['id']) {
                // found an IFC record
                //echo $linedata['id'] . "\n";
                $entity = new IFCSimpleEntity($linedata['class'], $linedata['data'], $linedata['id'], $linedata['raw']);
                $this->db->insert($entity);
//                $this->fireEvent('entity', new IFCEvent($entity));
            }
        }
        
        $this->db->commit();
        $this->feof = true; // file fully parsed         
    }
    
    public function setdb($temp, $folder, $filename){
        $this->db = new IFCDatastore($temp, $folder, $filename);
        return $this;
    }
    
    public function db(){
        return $this->db;
    }
    
    public function openFile() {
        if (!is_resource($this->fh)) {
            if (is_file($this->filename)) {
                $fh = fopen($this->filename, "r");
                if (!is_resource($fh)) {
                    throw new Exception("Could not open file $this->filename");
                }
            } else {
                throw new Exception("Filename $this->filename is not a file");
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
            $line .= $this->getNextLine();
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

    public function parseLineForData($line) {
        // first we catch the name
        $matches = [];
                
        if (preg_match('/^(?:\#(\d+)\s?=\s?)?(\w+\b)+(.*)$/', $line, $matches) == 1) {
            // match found
            $id = $matches[1];
            $name = $matches[2];
            $raw = $matches[3];
            
            //check if there is an ID
            if(!$id){
                return false;
            }
            
            $items = $this->split_args(rtrim($raw,';'));
            
            return [
                "id" => $id,
                "class" => $name,
                "raw"=> $raw,
                "data" => json_encode($items)
            ];
        } else {
            return false; // TODO or throw error?
        }
    }
    
    /** Function to split the raw argument list into nested arrays
     * 
     * @param string $raw
     * @return array 
     */
    private function split_args($raw){ 
        $output_array = array();        
        if($raw[0] === '(' && $raw[strlen($raw)-1] == ')'){
            $raw = (substr($raw,1,-1));
        }
        
//      split on \"..\", \'..\', "..", '..' Then split on (..), finaly split everything on comma's
//      $oldregex = "/\\\\\".*?\\\\\"|\\\'.*?\\\'|\\\".*?\\\"|\'.*?\'|[\(].*[\)]|[^\,\(\)\s][^\,\(\)]+[^\,\(\)\s]/";
        $regex = "/\\\\\".*?\\\\\"|\\\'.*?\\\'|\\\".*?\\\"|\'.*?\'|[\(].*[\)]|[^\,]+/";
        preg_match_all($regex, $raw,$output_array);
        foreach($output_array[0] as &$item){            
            if($item && ($item[0] == '(' && $item[strlen($item)-1] == ')')){
                $item = $this->split_args($item);
            }
        }
        return $output_array[0];        
    }
}
