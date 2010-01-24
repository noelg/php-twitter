<?php

/**
 * A minimalist PHP Twitter API.
 * Inspired by Mike Verdone's <http://mike.verdone.ca> Python Twitter Tools
 * 
 * @author Travis Dent <tcdent@gmail.com>
 * @copyright (c) 2009 Travis Dent.
 * @version 0.3
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
 * 
 * // View your lists. (Unfortunately, Twitter has adopted an inconsistent way 
 * // of exposing the list resources that doesn't quite fit our model.)
 * $twitter->{'username'}->lists();
 * 
 * 
 * Retrieving results in another format and setting the user agent string:
 * 
 * $twitter = new Twitter('username', 'password', array(
 *     'format' => "xml", 
 *     'user_agent' => "my-application/0.1"
 * ));
 * 
 * -or-
 * 
 * $twitter = new Twitter;
 * $twitter->set_option('format', "XML");
 * $twitter->set_option('user_agent', "my-application/0.1");
 * 
 */

class Twitter {
    
    public static $VERSION = 1;
    
    private $user;
    private $pass;
    private $uri;
    private $options;
    
    public function __construct($user=FALSE, $pass=FALSE, $options=array(), $uri=NULL){
        $this->user = $user;
        $this->pass = $pass;
        $this->uri = $uri;
        
        $this->options = array_merge(array(
            'user_agent' => "php-twitter/0.3", 
            'format' => 'json'
        ), $options);
        
        if(!in_array($this->options['format'], array('json', 'xml', 'rss', 'atom')))
            throw new TwitterException("Unsupported format: ".$this->options['format']);
    }
    
    public function set_option($key, $value){
        return $this->options[$key] = $value;
    }
    
    public function __get($key){
        return new Twitter($this->user, $this->pass, $this->options, $key);
    }
    
    public function __call($method, $args){
        $args = (count($args) && is_array($args[0]))? $args[0] : array();
        
        $curlopt = array(
            CURLOPT_RETURNTRANSFER => TRUE, 
            // Twitter returns a HTTP code 417 if we send an expectation.
            CURLOPT_HTTPHEADER => array('Expect:'),
            CURLOPT_HEADER => 0, 
            CURLOPT_USERAGENT => $this->options['user_agent']
        );
        
        if($this->user && $this->pass){
            $curlopt[CURLOPT_USERPWD] = sprintf("%s:%s", $this->user, $this->pass);
            $curlopt[CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
        }
        
        $uri = ($this->uri)? sprintf("%s/%s", $this->uri, $method) : $method;
        
        if(array_key_exists('id', $args)){
            $uri .= '/'.$args['id'];
            unset($args['id']);
        }
        
        if($method == 'search'){
            $url = sprintf("search.twitter.com/%s.%s", 
                $uri, $this->options['format']);
        }
        else {
            $url = sprintf("api.twitter.com/%d/%s.%s", 
                self::$VERSION, $uri, $this->options['format']);
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
        
        if($this->options['format'] == 'json')
            return json_decode($data);
        
        return $data;
    }
}

class TwitterException extends Exception {}

class TwitterResponseException extends TwitterException {
    
    public $response;
    public $data;
    
    public function __construct($response, $data){
        $this->response = $response;
        
        $message = sprintf("Response code %d from %s", 
            $this->response['http_code'], $this->response['url']);
        
        if(strpos($this->response['content_type'], "json")){
            $this->data = json_decode($data);
            $message .= " - ".$this->data->error;
        }
        
        parent::__construct($message, $this->response['http_code']);
    }
}

?>