<?php

use Serversidebim\ExpressReader;
use PHPUnit\Framework\TestCase;

/**
*  Corresponding Class to test YourClass class
*
*  For each class in your library, there should be a corresponding Unit-Test for it
*  Unit-Tests should be as much as possible independent from other test going on.
*
*  @author yourname
*/
class IFCSimpleEntityTest extends TestCase
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
        $var = new Serversidebim\IFCReader\IFCSimpleEntity("Maarten", ["one","two"]);
        $this->assertTrue(is_object($var));
        unset($var);
    }

    public function testEntity()
    {
        $data = [
        "A-230-M_FLOOR",
        "$",
        [
            "#920",
            "#1019"
        ],
        "$",
    ];
        $raw = "('A-230-M_FLOOR',$,(#920,#1019),$);";
        // create a new fake entity
        $ent = new Serversidebim\IFCReader\IFCSimpleEntity("IFCPRESENTATIONLAYERASSIGNMENT", $data, "1274", $raw);

        $this->assertEquals('IFCPRESENTATIONLAYERASSIGNMENT', $ent->class);

        // Load the IFC Scheme
        $contents = file_get_contents(__DIR__ . '/IFC4.ser');
        $reader = unserialize($contents);

        $ent->mapToScheme($reader);

        // TODO: check the scheme

        //var_dump($ent);
    }
}
