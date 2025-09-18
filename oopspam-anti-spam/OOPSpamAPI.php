<?php

namespace OOPSPAM\API;

/**
 * Helper class for sending a request to OOPSpam API
 * 
 * @author OOPSpam LLC
 * @link   https://www.oopspam.com/
 * @copyright Copyright (c) 2017 - 2025, oopspam.com
 */
class OOPSpamAPI {
    const version='v1';
    
    protected $api_key;
    protected $check_for_length;
    protected $oopspam_is_loggable;
    protected $oopspam_block_tempemail;
    protected $oopspam_block_vpns;
    protected $oopspam_block_datacenters;
    
    /**
    * Constructor
    * 
    * @param string $api_key
    * @return OOPSpamAPI
    */
    public function __construct($api_key, $check_for_length, $oopspam_is_loggable, $oopspam_block_tempemail, $oopspam_block_vpns, $oopspam_block_datacenters) {
        $this->api_key = $api_key;
        $this->check_for_length = $this->convertToString($check_for_length);
        $this->oopspam_is_loggable = $this->convertToString($oopspam_is_loggable);
        $this->oopspam_block_tempemail = $this->convertToString($oopspam_block_tempemail);
        $this->oopspam_block_vpns = $this->convertToString($oopspam_block_vpns);
        $this->oopspam_block_datacenters = $this->convertToString($oopspam_block_datacenters);
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
        $options = get_option('oopspamantispam_settings', array());

        // By default use OOPSpam API
        $apiEndpoint = "https://api.oopspam.com/";
        $headers = array(
            'content-type' => 'application/json',
            'accept' => 'application/json',
            'X-Api-Key' => $this->api_key
        );
        
        if (isset($options['oopspam_api_key_source']) && $options['oopspam_api_key_source'] == "RapidAPI") {
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

        $response = wp_remote_post($apiEndpoint.self::version.'/spamdetection', $args);

        // Debug response
        if (is_wp_error($response)) {
            error_log('OOPSpam API Error: ' . $response->get_error_message());
            return $response;
        }
        
        // Update API usage before returning
        $apiKeySource = isset($options['oopspam_api_key_source']) ? $options['oopspam_api_key_source'] : 'OOPSpamDashboard';
        $this->getAPIUsage($response, $apiKeySource);

        return $response;
    }

     /**
    * Submit false positives to OOPSpam's Reporting API
    * 
    * @param array $POSTparameters
    * 
    * @return string $jsonreply
    */
    protected function RequestToOOPSpamReportingAPI($POSTparameters) {

        $options = get_option('oopspamantispam_settings', array());

            $apiEndpoint = "https://api.oopspam.com/";
            $headers = array(
                'content-type' => 'application/json',
                'accept' => 'application/json',
                'X-Api-Key' => $this->api_key
            );
       
        $args = array(
            'body' => $POSTparameters,
            'headers' => $headers,
            'timeout' => 20
        );

        $jsonreply = wp_remote_post( $apiEndpoint.self::version.'/spamdetection/report', $args );        
        $apiKeySource = isset($options['oopspam_api_key_source']) ? $options['oopspam_api_key_source'] : 'OOPSpamDashboard';
        $this->getAPIUsage($jsonreply, $apiKeySource);

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
        if (is_wp_error($response)) {
            error_log('OOPSpam getAPIUsage Error: WP Error');
            return;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200 && $response_code !== 201) {
            error_log('OOPSpam getAPIUsage Error: Invalid response code ' . $response_code);
            return;
        }

        $headerResult = wp_remote_retrieve_headers($response);
        if (empty($headerResult)) {
            error_log('OOPSpam getAPIUsage Error: Empty headers');
            return;
        }

        // Default values
        $remaining = '0';
        $limit = '0';

        if ($currentEndpointSource == "OOPSpamDashboard") {
            // Check if headers exist before accessing them
            $remaining = isset($headerResult['X-RateLimit-Remaining']) ? $headerResult['X-RateLimit-Remaining'] : 
                        (isset($headerResult['x-ratelimit-remaining']) ? $headerResult['x-ratelimit-remaining'] : '0');
            $limit = isset($headerResult['X-RateLimit-Limit']) ? $headerResult['X-RateLimit-Limit'] : 
                    (isset($headerResult['x-ratelimit-limit']) ? $headerResult['x-ratelimit-limit'] : '0');
        } else {
            // RapidAPI headers
            $remaining = isset($headerResult['x-ratelimit-requests-remaining']) ? $headerResult['x-ratelimit-requests-remaining'] : '0';
            $limit = isset($headerResult['x-ratelimit-requests-limit']) ? $headerResult['x-ratelimit-requests-limit'] : '0';
        }

        // Direct database update for better performance
        global $wpdb;
        $usage_value = $remaining . '/' . $limit;
        $option_name = 'oopspamantispam_settings';
        
        // Get the serialized option value
        $serialized_options = $wpdb->get_var($wpdb->prepare(
            "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
            $option_name
        ));
        
        if ($serialized_options) {
            $options = maybe_unserialize($serialized_options);
            $options['oopspam_api_key_usage'] = $usage_value;
            
            // Update the serialized value directly in the database
            $wpdb->update(
                $wpdb->options,
                array('option_value' => maybe_serialize($options)),
                array('option_name' => $option_name)
            );
            
        } else {
            // If option doesn't exist yet, create it using standard WordPress function
            $options = array('oopspam_api_key_usage' => $usage_value);
            add_option($option_name, $options);
        }
        
        return $usage_value;
    }

    /**
    * Sends a request to OOPSpam API
    * 
    * @param string $content The content that we evaluate.
    * 
    * @return string It returns structured JSON, Score field as root field indicating the spam score
    */
    public function SpamDetection($content, $sender_ip, $email, $countryallowlistSetting, $languageallowlistSetting, $countryblocklistSetting) {
        $contextDetectionOptions = get_option('oopspamantispam_contextai_settings');
        $isContextualDetectionEnabled = isset($contextDetectionOptions['oopspam_is_contextai_enabled']) ? true : false;
        
        if (!$isContextualDetectionEnabled) {
            $context = "";
        } else {
            $context = isset($contextDetectionOptions["oopspam_website_context"]) ? $contextDetectionOptions["oopspam_website_context"] : "";
        }
        
        $parameters = array(
            'content' => $content,
            'context' => $context,
            'senderIP' => $sender_ip,
            'email' => $email,
            'checkForLength' => $this->check_for_length,
            'logIt' => $this->oopspam_is_loggable,
            'allowedLanguages' => $languageallowlistSetting,
            'allowedCountries' => $countryallowlistSetting,
            'blockedCountries' => $countryblocklistSetting,
            'blockTempEmail' => $this->oopspam_block_tempemail,
            'blockDC' => $this->oopspam_block_datacenters,
            'blockVPN' => $this->oopspam_block_vpns
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

        $contextDetectionOptions = get_option('oopspamantispam_contextai_settings');
        $isContextualDetectionEnabled = isset($contextDetectionOptions['oopspam_is_contextai_enabled']) ? true : false;

        if (!$isContextualDetectionEnabled) {
            $context = "";
        } else {
            $context = isset($contextDetectionOptions["oopspam_website_context"]) ? $contextDetectionOptions["oopspam_website_context"] : "";
        }


        $parameters=array(
            'content' => $content,
            'context' => $context,
            'senderIP' => $sender_ip,
            'email' => $email,
            'checkForLength' => $this->check_for_length,
            'allowedLanguages' => $languageallowlistSetting,
            'allowedCountries' => $countryallowlistSetting,
            'blockedCountries' => $countryblocklistSetting,
            'blockTempEmail' => $this->oopspam_block_tempemail,
            'blockDC' => $this->oopspam_block_datacenters,
            'blockVPN' => $this->oopspam_block_vpns,
            "shouldBeSpam" => $isSpam,
            "sensitivityLevel" => $currentSensitivityLevel
        );        

        $jsonreply=$this->RequestToOOPSpamReportingAPI(json_encode($parameters));
        
        return $jsonreply;
    }
 
}