<?php

function http_parse_headers( $header )
    {
        $retVal = array();
        $fields = explode("\r\n", preg_replace('/\x0D\x0A[\x09\x20]+/', ' ', $header));
        foreach( $fields as $field ) {
            if( preg_match('/([^:]+): (.+)/m', $field, $match) ) {
                $match[1] = preg_replace('/(?<=^|[\x09\x20\x2D])./e', 'strtoupper("\0")', strtolower(trim($match[1])));
                if( isset($retVal[$match[1]]) ) {
                    $retVal[$match[1]] = array($retVal[$match[1]], $match[2]);
                } else {
                    $retVal[$match[1]] = trim($match[2]);
                }
            }
        }
        return $retVal;
    }

/**
 * A minimalist PHP Twitter API.
 * Inspired by Mike Verdone's <http://mike.verdone.ca> Python Twitter Tools
 * 
 * @author Travis Dent <tcdent@gmail.com>
 * @copyright (c) 2009 Travis Dent.
 * @version 0.2.2
 * 
 * Public (unauthenticated) methods:
 * 
 * $twitter = new Twitter;
 * 
 * // Get the public timeline.
 * $entries = $twitter->statuses->public_timeline();
 * 
 * // Search.
 * $twitter->search(array('q' => 'foo'));
 * 
 * 
 * Protected (authenticated) methods:
 * 
 * $twitter = new Twitter('username', 'password');
 * 
 * // Get page two of the user's followers.
 * $entries = $twitter->statuses->followers(array('page' => 2));
 * 
 * // Send a direct message.
 * $twitter->direct_messages->new(array('user' => 12345, 'text' => 'foo'));
 */

class Twitter {
    
    public static $VERSION = 1;
    
    private $user;
    private $pass;
    private $format;
    private $uri;
    
    private $userAgent;
    
    private $arrReturnedHeaders;
    
    public function __construct($user=FALSE, $pass=FALSE, $format='json', $uri=NULL, $userAgent=NULL){
        if(!in_array($format, array('json', 'xml', 'rss', 'atom')))
            throw new TwitterException("Unsupported format: $format");
        
        $this->user = $user;
        $this->pass = $pass;
        $this->format = $format;
        $this->uri = $uri;
        $this->userAgent = $userAgent;
    }
    
    public function __get($key){
        return new Twitter($this->user, $this->pass, $this->format, $key);
    }
    
    public function __call($method, $args){
        $args = (count($args) && is_array($args[0]))? $args[0] : array();
        
        $uri = ($this->uri)? sprintf("%s/%s", $this->uri, $method) : $method;
        
        if(array_key_exists('id', $args))
            $uri .= '/'.$args['id']; unset($args['id']);
        
        return $this->exec(array($uri), $args);
    }
    
    public function exec($arrPath, $args = array()) {
        
        $curlopt = array(
            CURLOPT_RETURNTRANSFER => TRUE, 
            // Twitter returns a HTTP code 417 if we send an expectation.
            CURLOPT_HTTPHEADER => array('Expect:'),
            CURLOPT_HEADER => 0
        );
        
        if($this->user && $this->pass){
            array_merge($curlopt, array(
                CURLOPT_USERPWD => sprintf("%s:%s", $this->user, $this->pass), 
                CURLOPT_HTTPAUTH => CURLAUTH_BASIC
            ));
        }
        
        if ($this->userAgent) {
            array_merge($curlopt, array(
                CURLOPT_USERAGENT => $this->userAgent));
        }
        
        $method = $arrPath[0];
        
        $subdomain = ($method == 'search')? 'search' : 'api';
        
        $strUri = implode($arrPath, '/');
        
        if ($subdomain == 'search') {
            $url = sprintf("%s.twitter.com/%s.%s", 
                $subdomain, $strUri, $this->format);
        } else {
            $url = sprintf("%s.twitter.com/%d/%s.%s", 
                $subdomain, self::$VERSION, $strUri, $this->format);
        }
        
        if(in_array($method, array('new', 'create', 'update', 'destroy'))){
            $curlopt[CURLOPT_POST] = TRUE;
            if($args) $curlopt[CURLOPT_POSTFIELDS] = $args;
        }
        elseif($args){
            $url .= '?'.http_build_query($args);
        }
        
        $curl = curl_init($url);
        curl_setopt_array($curl, $curlopt);
        $data = curl_exec($curl);
        $meta = curl_getinfo($curl);
        curl_close($curl);
        
        if($meta['http_code'] != 200)
            throw new TwitterResponseException($meta, $data);
        
        var_dump($data);
        
        if($this->format == 'json')
            return json_decode($data);
        
        return $data;
    }
}

class TwitterException extends Exception {}

class TwitterResponseException extends TwitterException {
    
    var $http_code = 0;
    
    public function __construct($response, $data){
        
        $this->http_code = $response['http_code'];
        
        $message = sprintf("Response code %d from %s", 
            $response['http_code'], $response['url']);
        
        if(strpos($response['content_type'], "json")){
            $data = json_decode($data);
            $message .= " - ".$data->error;
        }
        
        parent::__construct($message, $response['http_code']);
    }
}

?>