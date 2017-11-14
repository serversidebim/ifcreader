<?php

use PHPUnit\Framework\TestCase;
use Serversidebim\IFCReader\IFCSimpleEntity;

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
            $this->assertInstanceOf(IFCSimpleEntity::class, $entity);
        })->parse();
    }

    public function testIndex()
    {
        $filename = realpath(dirname(__FILE__) . "/smallfile.ifc");
        $reader = new Serversidebim\IFCReader\IFCStepReader($filename);
        $count = 0;
        $reader->on('index', function ($event) use (&$count) {
            $this->assertCount(2, $event->data);
            $count++;

            if ($event->data['id'] == 9) {
                $ent = Serversidebim\IFCReader\IFCStepReader::parseLineForData($event->data['line']);
                $this->assertCount(1, $ent['data']);
                $data = $ent['data'];
                $this->assertCount(2, $data[0]);
                $this->assertEquals('0.', $data[0][0]);
                $this->assertEquals('0.', $data[0][1]);
                //var_dump($ent['data']);
            }
            if ($event->data['id'] == 1113) {
                $ent = Serversidebim\IFCReader\IFCStepReader::parseLineForData($event->data['line']);
                $this->assertCount(4, $ent['data']);
                //var_dump($ent['data']);
            }
            if ($event->data['id'] == 1239) {
                $ent = Serversidebim\IFCReader\IFCStepReader::parseLineForData($event->data['line']);
                $this->assertCount(11, $ent['data']);
                //var_dump($ent['data']);
            }
        })->index();

        $this->assertEquals(673, $count);
    }
}
