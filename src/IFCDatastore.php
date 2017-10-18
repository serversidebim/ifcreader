<?php

namespace Serversidebim\IFCReader;

use Exception;

/**
 *  IFCStepReader
 *
 * Class to create, insert and find contents of a database
 * in STEP format.
 *
 *  @author Leon Roeterdink
 */


class IFCDatastore{
    
    private $dbfile;
    private $db;
    private $temp;
    
    public function __construct($temp = TRUE, $folder = NULL, $filename = NULL){
        $this->temp = $temp;
        if($temp){
            $this->create_database($temp, $folder);
        }else{
            if(isset($folder) && isset($filename) && is_file($folder . $filename)){
                $this->connect($folder,$filename);
            }else{
                $this->create_database($temp, $folder, $filename);
            }
        }
    }
    
    public function __destruct(){
        $this->close_db();
    }
    
    public function create_database($temp = TRUE, $folder = NULL, $filename = NULL){
        if($temp){
            if (isset($folder)) {
                if ($folder == realpath($folder)) {
                    if (!is_dir($folder)) {
                        throw new Exception("Folder $folder is not a folder");
                    }
                } else {
                    throw new Exception("Folder $folder does not exist");
                }
            } else {
                $folder = sys_get_temp_dir();
            }
            $this->dbfile = tempnam($folder, 'IFCReader_');
        }else{
            if(is_dir($folder) && !is_file($folder . $filename)){
                $this->dbfile = $folder . $filename;
            }else{
                throw new Exception("Path " . $folder . $filename . " is not valid.");
            }            
        }
        $this->db = new \SQLite3($this->dbfile);
        return $this->db->exec('CREATE TABLE ifc (row_number INTEGER PRIMARY KEY ASC, class string, data string, raw string, hash string);');
    }
    
    public function connect($folder, $filename){
        if(is_file($folder . $filename)){
            $this->dbfile = $folder . $filename;
            $this->db = new \SQLite3($this->dbfile,SQLITE3_OPEN_READWRITE);
        }else{
             throw new Exception("Path " . $folder . $filename . " is not a file.");
        }
    }
    
    public function get($id){
        $stmt = $this->db->prepare('SELECT * FROM ifc WHERE row_number = :row_number');
        $stmt->bindValue(':row_number', $id);
        $result = $stmt->execute();
        return $result->fetchArray(SQLITE3_ASSOC);
    }
    
    public function next(){
        $stmt = $this->db->prepare("SELECT * FROM ifc WHERE hash is NULL order by row_number limit 1");
        $res = $stmt->execute();
        $result = $res->fetchArray(SQLITE3_ASSOC);
        if(isset($result['row_number'])){
            return $result;
        }else{
            return false;
        }
    }
    
    public function insert($entity){ 
        $stmt = $this->db->prepare("INSERT INTO ifc (row_number,class,data,raw,hash) VALUES (:row_number,:class,:data,:raw,NULL)");
        $stmt->bindValue(':row_number', $entity->id);
        $stmt->bindValue(':class', $entity->class);
        $stmt->bindValue(':data', json_encode($entity->data));
        $stmt->bindValue(':raw', $entity->raw);
        return $stmt->execute();
    }
    
    public function set_hash($id, $hash){
        $this->begin_transaction();
        $stmt = $this->db->prepare("UPDATE ifc set hash = :hash where row_number = :row_number");
        $stmt->bindValue(':row_number', $id);
        $stmt->bindValue(':hash', $hash);
        $result = $stmt->execute();
        $this->commit();
        return $result;
    }
    
    public function get_dbfile(){
        return $this->dbfile;
    }
    
    public function close_db(){
        $this->db->close();
        if(is_file($this->dbfile)){
           return unlink($this->dbfile);
        }else{
            return TRUE;
        }
    }
    
    public function begin_transaction(){
        return $this->db->exec("BEGIN TRANSACTION");        
    }
    
    public function commit(){
        $this->db->exec("COMMIT");      
    }
    
}