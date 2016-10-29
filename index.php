<?php
class BusTimes
{
    protected $requestBody;

    protected $realTimeInfoURL;

    protected $routeSlot;

    protected $countSlot;

    protected $buses;

    protected $speechString;

    public function __construct()
    {
        if (file_exists('config.php')) {
            include 'config.php';
            $this->realTimeInfoURL = $realTimeInfoURL;
        } else {
            die('Application configuration file unavailable');
        }

        // get POST body from request and extract into an array
        $body = file_get_contents('php://input');
        file_put_contents('alexa.log', $body);
        $this->requestBody = json_decode($body, true);
    }

    protected function getRequestSlots()
    {
        if (isset($this->requestBody['request']['intent']['slots']['route']['value'])) {
            $this->routeSlot = $this->requestBody['request']['intent']['slots']['route']['value'];
        }
        if (isset($this->requestBody['request']['intent']['slots']['count']['value'])) {
            $this->countSlot = $this->requestBody['request']['intent']['slots']['count']['value'];
        }
    }

    protected function extractBusData($html)
    {
        // Load all text in TD tags from web page
        $dom = new DOMDocument();
        $dom->loadHTML($html);
        $cells = $dom->getElementsByTagName('td');

        // create new array and strip any empty cells
        $newArray = array();
        foreach ($cells as $cell) {
            if ($cell->textContent != "\xC2\xA0") {
                $newArray[] = $cell->textContent;
            }
        }
        $count = count($newArray);

        $buses = array();

        $i = 0;
        while ($i < $count) {
            $buses[] = array($newArray[$i++], $newArray[$i++], $newArray[$i++]);
        }
        $this->buses = $buses;
    }

    protected function filterBusesByRoute()
    {
        // first filter by route
        $routeResult = array();
        foreach ($this->buses as $bus) {
            if ($bus[0] == $this->routeSlot) {
                $routeResult[] = $bus;
            }
        }
        $this->buses = $routeResult;
        return $this;
    }

    protected function filterBusesByCount()
    {
        if (count($this->buses) > $this->countSlot) {
            $countResult = $this->buses;
            $this->buses = null;
            $this->buses = array();
            for ($i = 0; $i < $this->countSlot; $i++) {
                $this->buses[] = $countResult[$i];
            }
        }
        return $this;
    }

    protected function filterBusesByNextBus() {
        $nextBus = [$this->buses[0]];
        $this->buses = null;
        $this->buses = $nextBus;
        return $this;
    }

    protected function renderSpeech()
    {
        if (!empty($this->buses)) {
            $this->speechString = 'The next bus is';

            $multipleRows = false;

            foreach ($this->buses as $bus) {
                if ($multipleRows === true) {
                    $this->speechString .= ', followed by';
                }
                $this->speechString .= ' the number ' . $bus[0] . ' to ' . $bus[1] . ' ';
                $dueTime = $bus[2];
                if ($dueTime == 'Due') {
                    // Bus due now
                    $this->speechString .= 'which is due now';
                } elseif (strpos($dueTime, 'Mins') !== false) {
                    // Bus due in x mins
                    $this->speechString .= 'which is due in ' . $dueTime;
                } else {
                    // Bus due at specific time
                    $this->speechString .= 'which is due at ' . $dueTime;
                }
                $multipleRows = true;
            }
        } else {
            $this->speechString = 'Sorry, there were no buses matching your criteria.';
        }
    }

    public function run()
    {
        // load HTML from real-time page
        $realTimeHTML = file_get_contents($this->realTimeInfoURL);
        $this->extractBusData($realTimeHTML);

        $this->getRequestSlots();

        // get speech text based on slots supplied
        if (isset($this->countSlot) && isset($this->routeSlot)) {

            //user asked for next {x} buses on {y} route
            $this   ->filterBusesByRoute()
                    ->filterBusesByCount()
                    ->renderSpeech();

        } elseif (isset($this->routeSlot)) {

            // user asked for next number {x} bus
            $this   ->filterBusesByRoute()
                    ->renderSpeech();

        } elseif (isset($this->countSlot)) {

            // user asked for next {x} buses
            $this   ->filterBusesByCount()
                    ->renderSpeech();

        } else {
            // user asked for next bus

            $this   ->filterBusesByNextBus()
                    ->renderSpeech();
        }

        $responseJSON = '{
    "version": "1.0",
    "response": {
        "outputSpeech": {
            "type": "PlainText",
             "text": "'.$this->speechString.'"
        },
        "shouldEndSession": true
    }
}';

        $responseLen = strlen($responseJSON);
        header('Content-Type: application/json;charset=UTF-8');
        header('Content-Length: '.$responseLen);
        echo $responseJSON;

    }
}
$alexa = new BusTimes();
$alexa->run();