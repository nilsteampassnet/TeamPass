<?php
class AXSILPortal_V1_Auth {
    var $server;

    private $url = "";
    private $AAId = "";
    private $apiKey = "";

    private $baseUrl = "";

    function setUrl($url) {
        $this->url = $url;
    }

    function setAAId($AAId) {
        $this->AAId = $AAId;
    }

    function setApiKey($apiKey) {
        $this->apiKey = $apiKey;
    }


    // Verify that you can open the URL from the web server.
    function create() {
        if ($this->url != "" && $this->AAId != "" && $this->apiKey != "") {
            $this->baseUrl = $this->url."".$this->apiKey."/".$this->AAId;
        } else {
            die("Cannot initialize Agses webservice without credentials, please set them in settings");
        }
    }

    function createAuthenticationMessage($apn, $createFlickerCode, $returnPath, $authenticationLevel, $hedgeId) {

        $serviceCall = filter_var($this->baseUrl."/authmessage/".$apn."/create/".$hedgeId, FILTER_SANITIZE_URL);

        $json = file_get_contents($serviceCall);
        $response = json_decode($json, true);

        return $response['flickerCode'];
    }

    /**
     * @param string $response
     * @param string $hedgeId
     */
    function verifyResponse($apn, $response, $hedgeId) {

        $serviceCall = filter_var($this->baseUrl."/authmessage/".$apn."/verify/".$hedgeId."/".$response, FILTER_SANITIZE_URL);

        $json = file_get_contents($serviceCall);
        $response = json_decode($json, true);

        return $response['response'];
    }

}
?>