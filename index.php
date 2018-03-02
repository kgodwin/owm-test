<?php
    require_once('config.php');

    try {
        $Weather = new Weather($sentry, $logger);
        
        if(!isset($_GET['guid'])) {
                $logger->debug("GUID Missing");
                echo json_encode(['error' => true, 'msg' => 'GUID Missing']);
        }
        else {
            $guid = (string)$_GET['guid'];
            if ($Weather->SetGUID($guid)) {
                if(isset($_GET['lat']) && isset($_GET['long']) && $_GET['lat'] > 0 && $_GET['long'] > 0) {
                    echo $Weather->ApiResponse($_GET['lat'], $_GET['long'], (int)$_GET['version']);
                }
                else {
                    echo $Weather->Response(['error' => true, 'msg' => 'missing lat and/or long'], "");
                    $logger->debug($guid.' made a request missing lat and/or long');
                }
            }
        }
    }
    catch (Exception $E) {
        $sentry->captureMessage($E);
        echo json_encode(['error' => true, 'msg' => 'internal server error']);
        $logger->error($E);
    }

    class Weather {
        private $guid;
        private $sentry;
        private $logger;
        function __construct($sentry, $logger) {
            $this->sentry = $sentry;
            $this->logger = $logger;
        }

        function SetGUID($guid) {
            if (preg_match('/^\{?[A-Z0-9]{8}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{12}\}?$/', $guid)) {
                $this->guid = $guid;
                return true;
            }
            $this->logger->info($guid.' is not a GUID');
            return false;
        }

        /*
            To extend, just add a new vDate method. i.e. v20180226 = version 2018-Feb-26

            To invoke, http://www.domain.tld/owm/?lat=35.0&long=139.0&version=20180228&guid=A98C5A1E-A742-4808-96FA-6F409E799937
        */
        function ApiResponse($lat, $long, $version="20180225") {
            $v = "v$version";
            if(method_exists($this, $v)) {
                return $this->Response($this->$v($lat, $long), $v);
            }
            else {
                $method = get_class_methods($this);
                $versions = [];
                foreach($method as $m) {
                    if(substr($m,0,1) == 'v') {
                        $versions[] = substr($m,1);
                    }
                }
                rsort($versions);
                $v = "v".$versions[0];
                return $this->Response($this->$v($lat, $long), $v);
            }
        }

        function Response($response, $v) {
            $response['version'] = $v;
            if($response['error']) {
                $this->sentry->captureMessage($response['msg']);
                $this->logger->error($this->guid, array($response));
                return json_encode($response);
            }
            else {
                $this->logger->debug($this->guid, $response);
                return json_encode($response);
            }
        }

        function v20180228($lat, $long) {
            return $this->OpenWeatherMap($lat, $long, 'imperial');
        }

        function v20180226($lat, $long) {
            return "v20180226"; // just a test stub
        }

        function OpenWeatherMap($lat, $long, $units) {
            $url = 'https://api.openweathermap.org/data/2.5/weather?lat='.(double)$lat.'&lon='.(double)$long.'&units='.$units.'&appid='.trim(file_get_contents('/opt/secrets/owm'));
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, $url); 
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($curl, CURLOPT_TIMEOUT, 3);
            $content = trim(curl_exec($curl));
            curl_close($curl);
            $content = json_decode($content,true);
            if(isset($content['main']['temp']) && isset($content['wind']['speed']) && isset($content['wind']['deg']) && isset($content['weather'][0]['description']) ) {
                $data = [];
                $data['temperature_fahrenheit'] = (double)$content['main']['temp'];
                $data['weather_description']    = (string)$content['weather'][0]['description'];
                $data['wind_speed_mph']         = (double)$content['wind']['speed'];
                $data['wind_direction']         = (string)$this->WindDirection($content['wind']['deg']);
                $data['wind_direction_degrees'] = (int)$content['wind']['deg'];
                $data['error']                  = false;
                return $data;
            }
            return ['error'=>true, 'msg' => $content['message']];
        }
    
        function WindDirection($degrees) {
            if ($degrees>337.5)
                return 'Northerly';
            if ($degrees>292.5)
                return 'North Westerly';
            if($degrees>247.5)
                return 'Westerly';
            if($degrees>202.5)
                return 'South Westerly';
            if($degrees>157.5)
                return 'Southerly';
            if($degrees>122.5)
                return 'South Easterly';
            if($degrees>67.5)
                return 'Easterly';
            if($degrees>22.5)
                return 'North Easterly';

            return 'Northerly';
        }
    
    }
