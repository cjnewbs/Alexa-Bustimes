<?php
require 'config.php';

// load HTML from real-time page
$realTimeHTML = file_get_contents($realTimeInfoURL);

// Load all text in TD tags from web page
$dom = new DOMDocument();
$dom->loadHTML($realTimeHTML);
$tables = $dom->getElementsByTagName('td');

// get speech text
$speechString = loadSpeech($tables);

$responseJSON = '{
    "version": "1.0",
    "response": {
        "outputSpeech": {
            "type": "PlainText",
             "text": "'.$speechString.'"
        },
        "shouldEndSession": true
    }
}';

$responseLen = strlen($responseJSON);
header('Content-Type: application/json;charset=UTF-8');
header('Content-Length: '.$responseLen);
echo $responseJSON;

// generate the
function loadSpeech($tables) {

    $outputString = 'The next bus is the ';

    $i =1;
    foreach ($tables as $table) {

        if ($i == 1) {
            $outputString .= $table->nodeValue.' to ';
        }
        if ($i == 3) {
            $outputString .= $table->nodeValue.' ';
        }
        if ($i == 5) {
            $dueTime = $table->nodeValue;
            if ($dueTime == 'Due') {
                // Bus due now
                $outputString .= 'which is due now';
            } elseif (strpos($dueTime, 'Mins') !== false) {
                // Bus due in x mins
                $outputString .= 'which is due in '.$dueTime;
            } else {
                // Bus due at specific time
                $outputString .= 'which is due at '. $dueTime;
            }
        }
        $i++;
    }
    //' in ' . $nextTime;
    return $outputString;
}