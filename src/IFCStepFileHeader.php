<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace Serversidebim\IFCReader;

/**
 * Description of IFCStepFileHeader
 *
 * @author Veem5
 */
class IFCStepFileHeader extends IFCFileHeader
{
    public function parseRaw($subject)
    {

        // In rare situaties, the HEADER; is immediately follewed by data,
        // add a newline after HEADER; in that case
        $subject = \preg_replace('@^(HEADER;)(\w)@mi', "$1\r\n$2", $subject, 1);

        $separator = "\r\n";
        $line = strtok($subject, $separator);

        while ($line !== false) {
            if ($data = IFCStepReader::parseLineForData($line)) {

                // set the data in the header
                $class = strtoupper($data['class']);

                if (!in_array($class, ["HEADER", "ENDSEC"])) {
                    $this->setByArray($class, $data['data']);
                }
            }

            // get the following line
            $line = strtok($separator);
        }
    }
}
