<?php

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
    
    public function __construct($user=FALSE, $pass=FALSE, $format='json', $uri=NULL){
        if(!in_array($format, array('json', 'xml', 'rss', 'atom')))
            throw new TwitterException("Unsupported format: $format");
        
        $this->user = $user;
        $this->pass = $pass;
        $this->format = $format;
        $this->uri = $uri;
    }
    
    public function __get($key){
        return new Twitter($this->user, $this->pass, $this->format, $key);
    }
    
    public function __call($method, $args){
        $args = (count($args) && is_array($args[0]))? $args[0] : array();
        
        $curlopt = array(
            CURLOPT_RETURNTRANSFER => TRUE, 
            // Twitter returns a HTTP code 417 if we send an expectation.
            CURLOPT_HTTPHEADER => array('Expect:')
        );
        
        if($this->user && $this->pass){
            array_merge($curlopt, array(
                CURLOPT_USERPWD => sprintf("%s:%s", $this->user, $this->pass), 
                CURLOPT_HTTPAUTH => CURLAUTH_BASIC
            ));
        }
        
        $uri = ($this->uri)? sprintf("%s/%s", $this->uri, $method) : $method;
        
        if(array_key_exists('id', $args))
            $uri .= '/'.$args['id']; unset($args['id']);
        
        $subdomain = ($method == 'search')? 'search' : 'api';
        $url = sprintf("%s.twitter.com/%d/%s.%s", 
             $subdomain, self::$VERSION, $uri, $this->format);
        
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
            throw new TwitterException($meta, $data);
        
        if($this->format == 'json')
            return json_decode($data);
        
        return $data;
    }
}

class TwitterException extends Exception {
    
    public function __construct($response, $data){
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