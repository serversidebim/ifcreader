<?php
use PHPUnit\Framework\TestCase;

/**
*  Corresponding Class to test YourClass class
*
*  For each class in your library, there should be a corresponding Unit-Test for it
*  Unit-Tests should be as much as possible independent from other test going on.
*
*  @author yourname
*/
class IFCStepReaderTest extends TestCase
{

  /**
  * Just check if the YourClass has no syntax error
  *
  * This is just a simple check to make sure your library has no syntax error. This helps you troubleshoot
  * any typo before you even use this library in a real project.
  *
  */
    public function testIsThereAnySyntaxError()
    {
        $var = new Serversidebim\IFCReader\IFCStepReader(null);
        $this->assertTrue(is_object($var));
        unset($var);
    }

    public function testLoad()
    {
        $this->markTestSkipped(
              'Temporarily disabled'
            );
        $filename = realpath(dirname(__FILE__) . "/smallfile.ifc");
        $reader = new Serversidebim\IFCReader\IFCStepReader($filename);
        $reader->load();

        $this->assertEquals("IFC2X3", $reader->schema());
    }


    public function testParse()
    {
        $filename = realpath(dirname(__FILE__) . "/smallfile.ifc");
        $reader = new Serversidebim\IFCReader\IFCStepReader($filename);
        $reader->load();

        $reader->on('entity', function ($event) {
            $entity = $event->data;
            //var_dump($entity);
        })->parse();
    }

    public function testIndex()
    {
        $filename = realpath(dirname(__FILE__) . "/smallfile.ifc");
        $reader = new Serversidebim\IFCReader\IFCStepReader($filename);
        $reader->index();

        // in the small file, ID 1236 should be at 45389
        $this->assertEquals(45389, $reader->find(1236));

        // in the large file, ID 35 should be at 124953968
      //$this->assertEquals(124953968, $reader->find(35));
    }
}
