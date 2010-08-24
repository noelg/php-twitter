<?php

namespace Twitter;
use Twitter\Exception;

class ResponseException extends Exception {

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