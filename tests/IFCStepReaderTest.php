<?php

use PHPUnit\Framework\TestCase;
use Serversidebim\ExpressReader\Reader;
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

    /**
     * @throws Exception
     */
    public function testLoad()
    {
        $filename = realpath(dirname(__FILE__) . "/smallfile.ifc");
        $reader = new Serversidebim\IFCReader\IFCStepReader($filename);
        $reader->load();

        $this->assertEquals("IFC2X3", $reader->schema());
    }


    /**
     * @throws Exception
     */
    public function testParse()
    {
        $filename = realpath(dirname(__FILE__) . "/smallfile.ifc");
        $reader = new Serversidebim\IFCReader\IFCStepReader($filename);
        $reader->load();

        $this->assertEquals("IFC2X3", $reader->header->FILE_SCHEMA->schema[0]);

        $reader->on('entity', function ($event) {
            $entity = $event->data;
            $this->assertInstanceOf(IFCSimpleEntity::class, $entity);
        })->parse();
    }

    /**
     * @throws Exception
     */
    public function testEntityMapper()
    {
        $filename = realpath(dirname(__FILE__) . "/smallfile.ifc");
        $reader = new Serversidebim\IFCReader\IFCStepReader($filename);
        $reader->load();

        // Load the IFC Scheme
        $contents = file_get_contents(__DIR__ . '/IFC2X3.exp');
        $express = new Reader();
        $express->parse($contents);

        $reader->on('entity', function ($event) use ($express) {
            $entity = $event->data;
            // Now map
            try {
                $entity->mapToScheme($express);
            } catch (Exception $e) {
                //var_dump('ERROR!');
                //var_dump($entity);
                //var_dump($express->getFullEntity($entity->class));


                throw new Exception($e);
            }
        })->parse();

        // Do a custom test on a line:
        $line = "#25230=IFCPROPERTYSINGLEVALUE('Reference',$,IFCLABEL('Basic Wall:22 KALKZANDSTEEN 100 (niet dragend)'),$);";
        $parsed = Serversidebim\IFCReader\IFCStepReader::parseLineForData($line);
        $entity = $reader->createEntityFromArray($parsed);
        //var_dump($parsed);
        //var_dump("SPECIFICATION:");
        //var_dump($express->getFullEntity($parsed['class']));
        $entity->mapToScheme($express);

        $line = "#157= IFCSURFACESTYLERENDERING(#156,0.,$,$,$,$,IFCNORMALISEDRATIOMEASURE(0.5),IFCSPECULAREXPONENT(64.),.NOTDEFINED.);";
        $parsed = Serversidebim\IFCReader\IFCStepReader::parseLineForData($line);
        $entity = $reader->createEntityFromArray($parsed);
        //var_dump($parsed);
        //var_dump("SPECIFICATION:");
        //var_dump($express->getFullEntity($parsed['class']));
        $entity->mapToScheme($express);

        $this->assertTrue(true);
    }

    /**
     * @throws Exception
     */
    public function testIndex()
    {
        $filename = realpath(dirname(__FILE__) . "/smallfile.ifc");
        $reader = new Serversidebim\IFCReader\IFCStepReader($filename);
        $count = 0;
        $reader->on('index', function ($event) use (&$count) {
            $this->assertCount(4, $event->data);
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
            if ($event->data['id'] == 94) {
                $ent = Serversidebim\IFCReader\IFCStepReader::parseLineForData($event->data['line']);
                $this->assertCount(9, $ent['data']);
            }
        })->index();

        $this->assertEquals(673, $count);
    }

    /**
     * @throws Exception
     */
    public function testMapParametersToTrueValues()
    {
        $filename = realpath(dirname(__FILE__) . "/smallfile.ifc");
        $reader = new Serversidebim\IFCReader\IFCStepReader($filename);
        $reader->load();

        // Load the IFC Scheme
        $contents = file_get_contents(__DIR__ . '/IFC2X3.exp');
        $express = new Serversidebim\ExpressReader\Reader();
        $express->parse($contents);

        $reader->on('entity', function ($event) use ($express) {
            $entity = $event->data;
            // Now map
            try {
                $entity->mapToScheme($express);
            } catch (Exception $e) {
                //var_dump('ERROR!');
                //var_dump($entity);
                //var_dump($express->getFullEntity($entity->class));


                throw new Exception($e);
            }
        })->parse();

        // Do a custom test on a line:
        $line = "#25230=IFCPROPERTYSINGLEVALUE('Reference',$,IFCLABEL('Basic Wall:22 KALKZANDSTEEN 100 (niet dragend)'),$);";
        $parsed = Serversidebim\IFCReader\IFCStepReader::parseLineForData($line);
        $entity = $reader->createEntityFromArray($parsed);
        $entity->mapToScheme($express);
        $clean = $entity->cleanData($express);

        $this->assertEquals("IFCLABEL", $clean['NominalValue']['type']);
        $this->assertEquals("Basic Wall:22 KALKZANDSTEEN 100 (niet dragend)", $clean['NominalValue']['value']);

        $line = "#157= IFCSURFACESTYLERENDERING(#156,0.,$,$,$,$,IFCNORMALISEDRATIOMEASURE(0.5),IFCSPECULAREXPONENT(64.),.NOTDEFINED.);";
        $parsed = Serversidebim\IFCReader\IFCStepReader::parseLineForData($line);
        $entity = $reader->createEntityFromArray($parsed);
        $entity->mapToScheme($express);
        $clean = $entity->cleanData($express);

        $this->assertTrue($clean['Transparency'] === 0.0);
        $this->assertTrue($clean['SpecularColour']['value'] === 0.5);
        $this->assertEquals('NOTDEFINED', $clean['ReflectanceMethod']);

        $line = "#204= IFCPROPERTYSINGLEVALUE('Base is Attached',$,IFCBOOLEAN(.F.),$);";
        $parsed = Serversidebim\IFCReader\IFCStepReader::parseLineForData($line);
        $entity = $reader->createEntityFromArray($parsed);
        $entity->mapToScheme($express);
        $clean = $entity->cleanData($express);

        $this->assertEquals('Base is Attached', $clean['Name']);
        $this->assertFalse($clean['NominalValue']['value']);

        //$line = "#1= IFCORGANIZATION($,'Autodesk Revit 2016 (ENU)',$,$,$)";
        $line = "#21= IFCDIRECTION((0.,0.,-1.));";
        $parsed = Serversidebim\IFCReader\IFCStepReader::parseLineForData($line);
        $entity = $reader->createEntityFromArray($parsed);
        $entity->mapToScheme($express);
        $clean = $entity->cleanData($express);

        $this->assertEquals(-1.0, $clean['DirectionRatios'][2]);

        $this->assertTrue(true);

        $line = "#48= IFCMEASUREWITHUNIT(IFCRATIOMEASURE(0.0174532925199433),#46);";
        $parsed = Serversidebim\IFCReader\IFCStepReader::parseLineForData($line);
        $entity = $reader->createEntityFromArray($parsed);
        $entity->mapToScheme($express);
        $clean = $entity->cleanData($express);

        $this->assertArrayHasKey('ValueComponent', $clean);
        $this->assertTrue(is_array($clean['ValueComponent']));
        $this->assertArrayHasKey('type', $clean['ValueComponent']);
        $this->assertEquals('IFCRATIOMEASURE', $clean['ValueComponent']['type']);
        $this->assertEquals('0.0174532925199433', $clean['ValueComponent']['value']);

        $this->assertTrue($clean['ValueComponent']['value'] === (float)'0.0174532925199433');
        $this->assertTrue($clean['ValueComponent']['orig_value'] === '0.0174532925199433');

        $line = "#100= IFCGEOMETRICREPRESENTATIONSUBCONTEXT('Axis','Model',*,*,*,*,#97,$,.GRAPH_VIEW.,$);";
        $parsed = Serversidebim\IFCReader\IFCStepReader::parseLineForData($line);
        $entity = $reader->createEntityFromArray($parsed);
        $entity->mapToScheme($express);
        $clean = $entity->cleanData($express);

        $this->assertArrayHasKey('CoordinateSpaceDimension', $clean);
        $this->assertEquals('*', $clean['CoordinateSpaceDimension']);
        $this->assertArrayHasKey('Precision', $clean);
        $this->assertEquals('*', $clean['Precision']);
        $this->assertEquals('$', $clean['TargetScale']);

        $this->assertTrue(true);

        $line = "#3581445= IFCGROUP('09V80LSyD7ggoZnYUvK2qL',#41,'Model Group:merk F'':1058164',$,'Model Group:merk F''');";
        $parsed = Serversidebim\IFCReader\IFCStepReader::parseLineForData($line);
        $entity = $reader->createEntityFromArray($parsed);
        $entity->mapToScheme($express);
        $clean = $entity->cleanData($express);

        $this->assertArrayHasKey('Name', $clean);
        $this->assertEquals('Model Group:merk F\':1058164', $clean['Name']);
        $this->assertArrayHasKey('ObjectType', $clean);
        $this->assertEquals('Model Group:merk F\'', $clean['ObjectType']);

        $this->assertTrue(true);

        $line = "#171575= IFCPROPERTYSINGLEVALUE('Type Mark',$,IFCTEXT('\X2\x25CB25CB\X0\'),$)";
        $parsed = Serversidebim\IFCReader\IFCStepReader::parseLineForData($line);
        $entity = $reader->createEntityFromArray($parsed);
        $entity->mapToScheme($express);
        $clean = $entity->cleanData($express);

        $this->assertArrayHasKey('NominalValue', $clean);
        $this->assertEquals('"\u0002%CB25CB\u0000\\\"', json_encode($clean['NominalValue']['value']));

        $this->assertTrue(true);
    }
}
