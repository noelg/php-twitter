<?php

/**
 * A minimalist PHP Twitter API.
 * Inspired by Mike Verdone's <http://mike.verdone.ca> Python Twitter Tools
 * 
 * @author Travis Dent <tcdent@gmail.com>
 * @copyright (c) 2009 Travis Dent.
 * @version 0.2.4
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
    
    public $user_agent = "php-twitter/0.2.4";
    
    private $user;
    private $pass;
    private $format;
    private $uri;
    
    public function __construct($user=FALSE, $pass=FALSE, $format='json', $user_agent=NULL, $uri=NULL){
        if(!in_array($format, array('json', 'xml', 'rss', 'atom')))
            throw new TwitterException("Unsupported format: $format");
        
        $this->user = $user;
        $this->pass = $pass;
        $this->uri = $uri;
        $this->format = $format;
        if($user_agent !== NULL) $this->user_agent = $user_agent;
    }
    
    public function __get($key){
        return new Twitter(
            $this->user, $this->pass, $this->format, $this->user_agent, $key);
    }
    
    public function __call($method, $args){
        $args = (count($args) && is_array($args[0]))? $args[0] : array();
        
        $curlopt = array(
            CURLOPT_RETURNTRANSFER => TRUE, 
            // Twitter returns a HTTP code 417 if we send an expectation.
            CURLOPT_HTTPHEADER => array('Expect:'),
            CURLOPT_HEADER => 0
        );
        
        if($this->user && $this->pass){
            $curlopt[CURLOPT_USERPWD] = sprintf("%s:%s", $this->user, $this->pass);
            $curlopt[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
        }
        
        if($this->user_agent)
            $curlopt[CURLOPT_USERAGENT] = $this->user_agent;
        
        $uri = ($this->uri)? sprintf("%s/%s", $this->uri, $method) : $method;
        
        if(array_key_exists('id', $args)){
            $uri .= '/'.$args['id'];
            unset($args['id']);
        }
        
        if($method == 'search'){
            $url = sprintf("search.twitter.com/%s.%s", $uri, $this->format);
        }
        else {
            $url = sprintf("api.twitter.com/%d/%s.%s", 
                self::$VERSION, $uri, $this->format);
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
        
        if($this->format == 'json')
            return json_decode($data);
        
        return $data;
    }
}

class TwitterException extends Exception {}

class TwitterResponseException extends TwitterException {
    
    public $http_code;
    
    public function __construct($response, $data){
        $this->http_code = $response['http_code'];
        
        $message = sprintf("Response code %d from %s", 
            $response['http_code'], $response['url']);
        
        if(strpos($response['content_type'], "json")){
            $data = json_decode($data);
            $message .= " - ".$data->error;
        }
        
        parent::__construct($message, $this->http_code);
    }
}

?>