<?php 

/**
*  Corresponding Class to test YourClass class
*
*  For each class in your library, there should be a corresponding Unit-Test for it
*  Unit-Tests should be as much as possible independent from other test going on.
*
*  @author yourname
*/
class IFCStepReaderTest extends PHPUnit_Framework_TestCase{
	
  /**
  * Just check if the YourClass has no syntax error 
  *
  * This is just a simple check to make sure your library has no syntax error. This helps you troubleshoot
  * any typo before you even use this library in a real project.
  *
  */
    public function testIsThereAnySyntaxError(){
          $var = new Serversidebim\IFCReader\IFCStepReader(null);
          $this->assertTrue(is_object($var));
          unset($var);
    }

    public function testLoad() {
        $this->markTestSkipped(
                'Temporarily disabled'
              );
        $filename = realpath(dirname(__FILE__) . "/smallfile.ifc");
        $reader = new Serversidebim\IFCReader\IFCStepReader($filename);
        $reader->load();

        $this->assertEquals("IFC2X3",$reader->schema()); 
    }

    public function testParseDB() {
        $filename = realpath(dirname(__FILE__) . "/smallfile.ifc");
        $reader = new Serversidebim\IFCReader\IFCStepReader($filename);
        $reader->load();
        $reader->setdb(FALSE, realpath(dirname(__FILE__)), "/database.db")->parse();
        while ($ent = $reader->db()->next()) {
            //add hash and procceed the class and variables
            $reader->db()->set_hash($ent['row_number'], '1');
        }
        $result = $reader->db()->get(298);        
        $this->assertEquals('IFCCARTESIANPOINT',$result['class']);
    }
    
    public function testParse(){
        $filename = realpath(dirname(__FILE__) . "/smallfile.ifc");
        $reader = new Serversidebim\IFCReader\IFCStepReader($filename);
        $reader->load();
        $reader->setdb(FALSE, realpath(dirname(__FILE__)), "/database.db")->parse();
        while ($ent = $reader->db()->next()) {
            //add hash and procceed the class and variables
            $reader->db()->set_hash($ent['row_number'], '1');
            $this->assertEquals($ent['raw'], $this->implode_recursive(json_decode(json_decode($ent['data']))) . ";");
        }
    }
    
    private function implode_recursive($array){
        foreach($array as &$item){
            if(is_array($item)){
                $item = $this->implode_recursive($item);
            }
        }
        return "(" . implode(",", $array) . ")";
    }
  
}