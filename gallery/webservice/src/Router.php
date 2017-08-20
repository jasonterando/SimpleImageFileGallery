<?php
namespace SimpleImageFileGallery;

use Exception;
use StdClass;

/**
 * Simple router to direct requests look for gallery data to DirectoryParser
 */
class Router {
    protected $albumProcessor;

    function __construct($albumProcessor) {
        $this->albumProcessor = $albumProcessor;
    }

    /**
     * Dispatch the inbound request to the AlbumProcessor
     *
     * @param string $requestUrl
     * @return void
     */
    function Dispatch($requestUrl) {
        try {
            $processed = false;
            if($requestUrl) {
                $p = stripos($requestUrl, 'info/'); 
                if(($p !== FALSE) && ($p == 0)) {
                    $processed = true;
                    $result = $this->albumProcessor->find(substr($requestUrl, 5));
                } 

                if(! $processed) {
                    $p = stripos($requestUrl, 'thumbnail/');
                    if(($p !== FALSE) && ($p == 0)) {
                        $processed = true;
                    }
                    $result = $this->albumProcessor->getThumbnail(substr($requestUrl, 10));
                }
            }
            if($processed) {
                http_response_code(200);
            } else {
                http_response_code(404);
                $result = new StdClass();
                $result->ErrorMessage = "Invalid Request";
            }
        } catch(Exception $ex) {
            http_response_code(400);
            $result = new StdClass();
            $result->ErrorMessage = $ex->getMessage();
        }
        header('Content-Type: application/json');
        echo json_encode($result);
    }
}