<?php
require 'config.php';

// get POST body from request and extract into an array
$body = file_get_contents('php://input');
$body = json_decode($body, true);

// get slots from request
if (isset($body['request']['intent']['slots']['route']['value'])) {
    $route = $body['request']['intent']['slots']['route']['value'];
}
if (isset($body['request']['intent']['slots']['count']['value'])) {
    $count = $body['request']['intent']['slots']['count']['value'];
}

// load HTML from real-time page
$realTimeHTML = file_get_contents($realTimeInfoURL);

// Load all text in TD tags from web page
$dom = new DOMDocument();
$dom->loadHTML($realTimeHTML);
$cells = $dom->getElementsByTagName('td');

// strip empty elements and re-order index
$busses = toBusArray($cells);

// get speech text based on slots supplied
if (isset($route) && isset($count)) {
    //user asked for next {x} busses on {y} route
    $filteredBusses = getNextBussesByRouteAndCount($busses, $route, $count);
    $speechString = loadSpeech($filteredBusses);

} elseif (isset($route)) {
    // user asked for next number {x} bus
    $filteredBusses = getNextBussesByRoute($busses, $route);
    $speechString = loadSpeech($filteredBusses);

} elseif (isset($count)) {
    // user asked for next {x} busses
    $filteredBusses = getNextBussesByCount($busses, $count);
    $speechString = loadSpeech($filteredBusses);


} else {
    // user asked for next bus
    $speechString = getNextBus($busses);
}

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

// generate the speech
function loadSpeech($busses) {

    if (!empty($busses)) {
        $outputString = 'The next bus is';

        $multipleRows = false;

        foreach ($busses as $bus) {
            if ($multipleRows === true) {
                $outputString .= ', followed by';
            }
            $outputString .= ' the number ' . $bus[0] . ' to ' . $bus[1] . ' ';
            $dueTime = $bus[2];
            if ($dueTime == 'Due') {
                // Bus due now
                $outputString .= 'which is due now';
            } elseif (strpos($dueTime, 'Mins') !== false) {
                // Bus due in x mins
                $outputString .= 'which is due in ' . $dueTime;
            } else {
                // Bus due at specific time
                $outputString .= 'which is due at ' . $dueTime;
            }
            $multipleRows = true;
        }
    } else {
        $outputString = 'Sorry, there were no busses matching your criteria.';
    }
    return $outputString;

}

function getNextBussesByRouteAndCount($busses, $route, $count) {

    // first filter by route
    $routeResult = array();
    foreach ($busses as $bus) {
        if ($bus[0] == $route) {
            $routeResult[] = $bus;
        }
    }

    // next filter by count
    if (count($routeResult) > $count) {
        $countResult = array();
        $i = 0;
        while ($count > count($countResult)) {
            $countResult[] = $routeResult[$i];
            $i++;
        }
        return $countResult;
    } else {
        return $routeResult;
    }
}

function getNextBussesByCount($busses, $count)
{
    // filter by count
    if (count($busses) > $count) {
        $countResult = array();
        for ($i = 0; $i < $count; $i++) {
            $countResult[] = $countResult[$i];
        }
        return $countResult;
    } else {
        return $busses;
    }
}

function getNextBussesByRoute($busses, $route) {
    // first filter by route
    $routeResult = array();
    foreach ($busses as $bus) {
        if ($bus[0] == $route) {
            $routeResult[] = $bus;
        }
    }
    return $routeResult;
}

function getNextBus($busses) {
    return loadSpeech([$busses[0]]);
}

function toBusArray($cells) {
    // create new array and strip any empty cells
    $newArray = array();
    foreach ($cells as $cell) {
        if ($cell->textContent != "\xC2\xA0") {
            $newArray[] = $cell->textContent;
        }
    }
    $count = count($newArray);

    $busses = array();

    $i = 0;
    while ($i < $count) {
        $busses[] = array($newArray[$i++], $newArray[$i++], $newArray[$i++]);
    }
    return $busses;
}