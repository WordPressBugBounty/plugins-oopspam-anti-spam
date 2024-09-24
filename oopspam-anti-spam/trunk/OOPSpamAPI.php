<?php
/**
 * Helper class for sending a request to OOPSpam API
 * 
 * @author OOPSpam LLC
 * @link   https://www.oopspam.com/
 * @copyright Copyright (c) 2017 - 2024, oopspam.com
 */
class OOPSpamAPI {
    const version='v1';
    
    protected $api_key;
    protected $check_for_length;
    protected $oopspam_is_loggable;
    protected $oopspam_block_tempemail;
    
    /**
    * Constructor
    * 
    * @param string $api_key
    * @return OOPSpamAPI
    */
    public function __construct($api_key, $check_for_length, $oopspam_is_loggable, $oopspam_block_tempemail ) {
        $this->api_key = $api_key;
        $this->check_for_length = $this->convertToString($check_for_length);
        $this->oopspam_is_loggable = $this->convertToString($oopspam_is_loggable);
        $this->oopspam_block_tempemail = $this->convertToString($oopspam_block_tempemail);
    }
    
     /**
    * Convert 0 & 1 values to boolean type
    */
    public function convertToString($value)
    {
        return $value ? "true" : "false";
    }
    /**
    * Calls the Web Service of OOPSpam API
    * 
    * @param array $POSTparameters
    * 
    * @return string $jsonreply
    */
    protected function RequestToOOPSpamAPI($POSTparameters) {

        $options = get_option('oopspamantispam_settings');

        // By default use OOPSpam API
        $apiEndpoint = "https://api.oopspam.com/";
        $headers = array(
            'content-type' => 'application/json',
            'accept' => 'application/json',
            'X-Api-Key' => $this->api_key
        );
        
        if ($options['oopspam_api_key_source'] == "RapidAPI") {
            $apiEndpoint = "https://oopspam.p.rapidapi.com/";
            $headers = array(
                'content-type' => 'application/json',
                'accept' => 'application/json',
                'x-rapidapi-key' => $this->api_key
            );
        }
       
        $args = array(
            'body' => $POSTparameters,
            'headers' => $headers,
            'timeout' => 20
        );

        $jsonreply = wp_remote_post( $apiEndpoint.self::version.'/spamdetection', $args );        
        $this->getAPIUsage($jsonreply, $options['oopspam_api_key_source']);

        return $jsonreply;
    }

     /**
    * Submit false positives to OOPSpam's Reporting API
    * 
    * @param array $POSTparameters
    * 
    * @return string $jsonreply
    */
    protected function RequestToOOPSpamReportingAPI($POSTparameters) {

        $options = get_option('oopspamantispam_settings');

        // By Default use RapidAPI
        // $apiEndpoint = "https://oopspam.p.rapidapi.com/";
        // $headers = array(
        //     'content-type' => 'application/json',
        //     'accept' => 'application/json',
        //     'x-rapidapi-key' => $this->api_key
        // );
        
        // if ($options['oopspam_api_key_source'] == "OOPSpamDashboard") {
            $apiEndpoint = "https://api.oopspam.com/";
            $headers = array(
                'content-type' => 'application/json',
                'accept' => 'application/json',
                'X-Api-Key' => $this->api_key
            );
        // }
       
        $args = array(
            'body' => $POSTparameters,
            'headers' => $headers,
            'timeout' => 20
        );

        $jsonreply = wp_remote_post( $apiEndpoint.self::version.'/spamdetection/report', $args );        
        $this->getAPIUsage($jsonreply, $options['oopspam_api_key_source']);

        return $jsonreply;
    }

    /**
    * Retrieve usage from HTTP response
    * 
    * @param string $response The HTTP response
    * 
    * @return string API usage appended as string: "0/0". First value is remaining, the second one is the limit.
    */
    public function getAPIUsage($response, $currentEndpointSource)
    {       
    
        $headerResult = wp_remote_retrieve_headers($response);
        $options = get_option('oopspamantispam_settings');
        if ($currentEndpointSource == "OOPSpamDashboard") {
            $options['oopspam_api_key_usage'] = strval($headerResult['X-RateLimit-Remaining']."/".$headerResult['X-RateLimit-Limit']);
        } else {
            $options['oopspam_api_key_usage'] = strval($headerResult['x-ratelimit-requests-remaining']."/".$headerResult['x-ratelimit-requests-limit']);
        }
        update_option('oopspamantispam_settings', $options );
    }

    /**
    * Sends a request to OOPSpam API
    * 
    * @param string $content The content that we evaluate.
    * 
    * @return string It returns structured JSON, Score field as root field indicating the spam score
    */
    public function SpamDetection($content, $sender_ip, $email, $countryallowlistSetting, $languageallowlistSetting, $countryblocklistSetting) {
        $parameters=array(
            'content' => $content,
            'senderIP' => $sender_ip,
            'email' => $email,
            'checkForLength' => $this->check_for_length,
            'logIt' => $this->oopspam_is_loggable,
            'allowedLanguages' => $languageallowlistSetting,
            'allowedCountries' => $countryallowlistSetting,
            'blockedCountries' => $countryblocklistSetting,
            'blockTempEmail' => $this->oopspam_block_tempemail
        );

        $jsonreply=$this->RequestToOOPSpamAPI(json_encode($parameters));
        
        return $jsonreply;
    }

     /**
    * Submit a request to OOPSpam API
    * 
    * @param string $content The content that we evaluate.
    * 
    * @return string {message: "success"} in case of successful request
    */
    public function Report($content, $sender_ip, $email, $countryallowlistSetting, $languageallowlistSetting, $countryblocklistSetting, $isSpam) {

        $options = get_option('oopspamantispam_settings');
        $currentSensitivityLevel = $options["oopspam_spam_score_threshold"];
        $parameters=array(
            'content' => $content,
            'senderIP' => $sender_ip,
            'email' => $email,
            'checkForLength' => $this->check_for_length,
            'allowedLanguages' => $languageallowlistSetting,
            'allowedCountries' => $countryallowlistSetting,
            'blockedCountries' => $countryblocklistSetting,
            "shouldBeSpam" => $isSpam,
            "sensitivityLevel" => $currentSensitivityLevel
        );
        
        $jsonreply=$this->RequestToOOPSpamReportingAPI(json_encode($parameters));
        
        return $jsonreply;
    }
 
}