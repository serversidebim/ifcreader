<?php

/**
 *  Corresponding Class to test YourClass class
 *
 *  For each class in your library, there should be a corresponding Unit-Test for it
 *  Unit-Tests should be as much as possible independent from other test going on.
 *
 *  @author yourname
 */
class IFCStepFileHeaderTest extends PHPUnit_Framework_TestCase {

    /**
     * Just check if the YourClass has no syntax error 
     *
     * This is just a simple check to make sure your library has no syntax error. This helps you troubleshoot
     * any typo before you even use this library in a real project.
     *
     */
    public function testIsThereAnySyntaxError() {
        $var = new Serversidebim\IFCReader\IFCStepFileHeader;
        $this->assertTrue(is_object($var));
        unset($var);
    }

    public function testParseHeader() {
        $headerstring = "HEADER;

/******************************************************************************************
* STEP Physical File produced by: The EXPRESS Data Manager Version 5.02.0100.07 : 28 Aug 2013
* Module:                         EDMstepFileFactory/EDMstandAlone
* Creation date:                  Fri May 05 15:27:06 2017
* Host:                           L603014
* Database:                       D:\Users\veem5\AppData\Local\Temp\{DCA5F9F4-6A29-4EB8-AA26-75F6D95D14E8}\ifc
* Database version:               5507
* Database creation date:         Fri May 05 15:27:04 2017
* Schema:                         IFC2X3
* Model:                          DataRepository.ifc
* Model creation date:            Fri May 05 15:27:04 2017
* Header model:                   DataRepository.ifc_HeaderModel
* Header model creation date:     Fri May 05 15:27:04 2017
* EDMuser:                        sdai-user
* EDMgroup:                       sdai-group
* License ID and type:            5605 : Permanent license. Expiry date: 
* EDMstepFileFactory options:     020000
******************************************************************************************/
FILE_DESCRIPTION(('ViewDefinition [CoordinationView]'),'2;1');
FILE_NAME('','2017-05-05T15:27:06',(''),(''),'The EXPRESS Data Manager Version 5.02.0100.07 : 28 Aug 2013','20150220_1215(x64) - Exporter 16.0.428.0 - Default UI','');
FILE_SCHEMA(('IFC2X3'));
ENDSEC;";
        
        $header = new Serversidebim\IFCReader\IFCStepFileHeader();
        $header->parseRaw($headerstring);
                
        $this->assertEquals(['IFC2X3'],$header->FILE_SCHEMA->schema);
        
        $this->assertEquals($header->SECTION_CONTEXT->context, NULL);
        
    }

}