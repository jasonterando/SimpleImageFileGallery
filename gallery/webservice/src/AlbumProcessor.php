<?php
namespace SimpleImageFileGallery;

use Exception;
use InvalidArgumentException;
use StdClass;
use Imagick;

/**
 * Class to handle the heavy lifting of navigating through an album directory, building thumbnail images
 * and returning useful viewmodels for rendering
 */
class AlbumProcessor {

    protected $thumbnailWidth;
    protected $thumbnailHeight;
    protected $baseImageUrl;
    protected $baseImagePath;
    protected $supportedFileExts;
    protected $convertSlashes;

    function __construct($thumbnailMaxWidth, $thumbnailMaxHeight, $baseImageUrl, $baseImagePath, $supportedFileExts) {
        $this->thumbnailMaxWidth = $thumbnailMaxWidth;
        $this->thumbnailMaxHeight = $thumbnailMaxHeight;
        $this->baseImageUrl = $this->forceTrailingSlash($baseImageUrl);
        $this->baseImagePath = $baseImagePath;
        $this->supportedFileExts = $supportedFileExts;
        if($this->baseImagePath) {
            $len = strlen($this->baseImagePath);
            if($len > 0) {
                if($this->baseImagePath[$len - 1] == DIRECTORY_SEPARATOR) {
                    $this->baseImagePath = substr($this->baseImagePath, 0, $len - 1);
                }
            }
        }
        $this->convertSlashes = DIRECTORY_SEPARATOR != '/';
    }

    /**
     * Convert URL to local path information
     *
     * @param string $url
     * @return object
     */
    function urlToLocalPathInfo($url) {
        $i = stripos($url, $this->baseImageUrl);
        if(($i !== FALSE) && ($i == 0)) {
            $url = substr($url, strlen($this->baseImageUrl));
            if(strlen($url) > 0) {
                if($url[0] != '/') {
                    $url = '/' . $url;
                }
            }
        }
        $url = ($this->convertSlashes ? str_replace('/', DIRECTORY_SEPARATOR, $url) : $url);
        if($url[0] != '/') $url = '/' . $url;
        $path = $this->baseImagePath . $url;

        // Remove the trailing slash if it's there
        $len = strlen($path);
        if($len > 0) {
            if($path[$len - 1] == '/') {
                $path = substr($path, 0, $len - 1);
            }
        }

        return $this->localPathToPathInfo($path);
    }

    /**
     * Return information for the specified path like parentDirectory, filename, extensoin
     *
     * @param string $path
     * @return object
     */
    function localPathToPathInfo($path) {
        if((! $path) || (strlen($path) == 0)) {
            return null;
        }

        $results = null;
        if(is_dir($path)) {
            $results = new StdClass();
            $results->path = $path;
            $results->isDirectory = true;
        } else if(is_file($path)) {
            $results = new StdClass();
            $results->path = $path;
            $results->isDirectory = false;
        };
        
        if($results != null) {
            $pathInfo = pathinfo($path);
            $results->parentDirectory = array_key_exists('dirname', $pathInfo) ? $pathInfo['dirname'] : '';
            $results->filename = $pathInfo['basename'];
            $results->filenameNoExt = $pathInfo['filename'];
            $results->extension = array_key_exists('extension', $pathInfo) ? $pathInfo['extension'] : '';
        }

        return $results;
    }

    /**
     * Convert local directory or directory & file to a relative data URL
     *
     * @param string $directory
     * @param string $filename
     * @return string
     */
    function localPathToDataUrl($directory, $filename = null) {
        $i = stripos($directory, $this->baseImagePath);
        if(($i !== FALSE) && ($i == 0)) {
            $directory = substr($directory, strlen($this->baseImagePath));
        }
        
        $url = $this->forceTrailingSlash($this->convertSlashes ? str_replace(DIRECTORY_SEPARATOR, '/', $directory) : $directory);
        if($filename) {
            $url .= $filename;
        }
        return $url;
    }

    /**
     * Convert local directory or directory and file to an album URL
     *
     * @param string $filePath
     * @return string
     */
    function localFilePathToAlbumUrl($filePath) {
        $i = stripos($filePath, $this->baseImagePath);
        if(($i !== FALSE) && ($i == 0)) {
            $filePath = substr($filePath, strlen($this->baseImagePath));
        }
        $url = ($this->convertSlashes ? str_replace(DIRECTORY_SEPARATOR, '/', $filePath) : $filePath);
        if(strlen($url) > 0) {
            if($url[0] == '/') {
                $url = substr($url, 1);
            }
        }
        return $this->baseImageUrl . $url;
    }

    /**
     * Force the URL to have a trailing slash
     *
     * @param string $url
     * @return string
     */
    function forceTrailingSlash($url) {
        $len = strlen($url);
        if($len > 0) {
            if($url[$len - 1] != '/') {
                $url .= '/';
            }
        } else {
            $url = '/';
        }
        return $url;
    }

    /**
     * Find the asset described by requestUrl
     *
     * @param string $url - the URL of the gallery location being requested
     * @return object
     */
    function find($url) {

        $match = $this->urlToLocalPathInfo($url);
        if(! $match) {
            return null;
        }

        if($match->isDirectory) {
            $result = $this->getDirectory($match, true);

            // Build the parental hierarchy
            $resultToUpdate = &$result;
            $matchToCheck = $match;
            $offset = 0;
            while($matchToCheck) {
                if(property_exists($matchToCheck, 'parentDirectory') && ($matchToCheck->path != $this->baseImagePath)) {
                    if($matchToCheck->parentDirectory != '.' && $matchToCheck->parentDirectory != '..') {
                        $matchToCheck = $this->localPathToPathInfo($matchToCheck->parentDirectory);
                    } else {
                        $matchToCheck = null;
                    }
                } else {
                    $matchToCheck = null;
                }
 
                if($matchToCheck && $matchToCheck->isDirectory) {
                    $parent = $this->getDirectory($matchToCheck, false);
                    if($parent) {
                        $resultToUpdate->parent = $parent;
                        $resultToUpdate = &$resultToUpdate->parent;
                    }
                }
            }
        } else {
            $result = $this->getFile($match);
        }

        return $result;
    }

    /**
     * For the url referring to an album or image file, return a thumbnail,
     * generating one if it doesn't already exist
     *
     * @param string $url
     * @return object
     */
    function getThumbnail($url) {
        $pathInfo = $this->urlToLocalPathInfo($url);
        if($pathInfo) {
            if($pathInfo->isDirectory) {
                $url = $this->localFilePathToAlbumUrl($this->getDirectoryThumbnail($pathInfo, true));
            } else {
                $url = $this->localFilePathToAlbumUrl($this->getImageThumbnail($pathInfo, true));
            }
            if($url) {
                $result = new StdClass();
                $result->thumbnailUrl = $url;
                return $result;
            }
        }
        throw new InvalidArgumentException("Invalid gallery path");
    }

    /**
     * Return information about the specified directory
     *
     * @param object $pathInfo
     * @param boolean $recurse
     * @return object
     */
    function getDirectory($pathInfo, $descending) {
        if(! $pathInfo) {
            return null;
        }
        
        if(! $pathInfo->isDirectory) {
            throw new Exception("$pathInfo->path is not a directory");
        }

        if(strcasecmp($pathInfo->filename, 'thumbnails') == 0) {
            return null;
        }

        $result = new StdClass();
        $result->name = $pathInfo->filename;
        $result->type = 'directory';
        $result->dataUrl = $this->localPathToDataUrl($pathInfo->path);
        $result->modified = date('Y-m-d H:i:s', filemtime($pathInfo->path));

        if($descending) {
            // If we are descending through the next level of hierarchy, get all child directories and files
            $result->children = [];
            foreach(scandir($pathInfo->path) as $item) {
                if($item[0] == '.' || $item == 'thumbnails') continue;
                $childInfo = $this->localPathToPathInfo($pathInfo->path . DIRECTORY_SEPARATOR . $item);
                if($childInfo) {
                    if($childInfo->isDirectory) {
                        $child = $this->getDirectory($childInfo, false);
                    } else {
                        $child = $this->getFile($childInfo, false);
                    }
                }
                if($child) {
                    $result->children[] = $child;
                }
            }
        }

        $thumbnailPath = $this->getDirectoryThumbnail($pathInfo, false);
        if($thumbnailPath) {
            $result->thumbnailUrl = $this->localFilePathToAlbumUrl($thumbnailPath);
        }

        $infoFileName = $pathInfo->path . DIRECTORY_SEPARATOR . 'directory.txt';
        $this->mergeInfo($infoFileName, $result);
        
        return $result;
    }

    /**
     * Return the path to the thumbnail for the specified directory if it exists 
     * (or if $forceCreation is true), directory thumbnails are generated based
     * upon its contents
     *
     * @param object $pathInfo
     * @param boolean $forceCreation
     * @return string
     */
    function getDirectoryThumbnail($pathInfo, $forceCreation = false) {
        if($pathInfo->isDirectory) {
            $exists = false;

            $thumbnailDirectory = $pathInfo->path . DIRECTORY_SEPARATOR . 'thumbnails';
            if(! is_dir($thumbnailDirectory)) {
                mkdir($thumbnailDirectory);
            }

            $thumbnailPath = $thumbnailDirectory . DIRECTORY_SEPARATOR . 'directory.jpg';
            
            if(file_exists($thumbnailPath)) {
                return $thumbnailPath;
            }

            if(! $forceCreation) {
                return null;
            }

            // Find the first image thumbnail in the directory, if there is one
            foreach(scandir($pathInfo->path) as $item) {
                if($item[0] == '.' || $item == 'thumbnails') continue;
                $itemPathInfo = $this->localPathToPathInfo($pathInfo->path . DIRECTORY_SEPARATOR . $item);
                if($itemPathInfo) {
                    if(! $itemPathInfo->isDirectory) {
                        $itemThumbnailPath = $this->getImageThumbnail($itemPathInfo, true);
                        if($itemThumbnailPath) {
                            // Copy the thumbnail and return the location
                            copy($itemThumbnailPath, $thumbnailPath);
                            return $thumbnailPath;
                        }
                    }
                }
            }

            // Iterate through folders, looking for an image
            foreach(scandir($pathInfo->path) as $item) {
                if($item[0] == '.' || $item == 'thumbnails') continue;
                $itemPathInfo = $this->localPathToPathInfo($pathInfo->path . DIRECTORY_SEPARATOR . $item);
                if($itemPathInfo) {
                    if($itemPathInfo->isDirectory) {
                        $itemThumbnailPath = $this->getDirectoryThumbnail($itemPathInfo, true);
                        if($itemThumbnailPath) {
                            // Copy the thumbnail and return the location
                            copy($itemThumbnailPath, $thumbnailPath);
                            return $thumbnailPath;
                        }
                    }
                }
            }
        }
        
        // There is no thumbnail...
        return null;
    }

    /**
     * Return the path of the thumbnail for the specified image file if it exists,
     * or generate it if $forceCreation is true
     *
     * @param object $pathInfo
     * @param boolean $forceCreation
     * @return string
     */
    function getImageThumbnail($pathInfo, $forceCreation = false) {
        if($pathInfo && (! $pathInfo->isDirectory)) {
            if(! $this->isImageFileExtension($pathInfo->extension)) {
                return null;
            }

            $thumbnailDirectory = $pathInfo->parentDirectory . DIRECTORY_SEPARATOR . 'thumbnails';
            if(! is_dir($thumbnailDirectory)) {
                mkdir($thumbnailDirectory);
            }

            $thumbnailPath = $thumbnailDirectory . DIRECTORY_SEPARATOR . $pathInfo->filenameNoExt . '-thumbnail.jpg';

            if(file_exists($thumbnailPath)) {
                return $thumbnailPath;
            }

            if(! $forceCreation) {
                return null;
            }

            try {
                $im = new Imagick($pathInfo->path);
                $im->thumbnailImage($this->thumbnailMaxWidth, $this->thumbnailMaxHeight, true, false);
                $im->setImageFormat('jpg');

                file_put_contents($thumbnailPath, $im->getImageBlob());

                $im->clear();
                $im->destroy();
                return $thumbnailPath;
            } catch(Exception $ex) {
                error_log("Unable to generate thumbnail for " . $pahInfo->path . ", " . $ex->getMessage());
            }
        }
        return null;
    }

    /**
     * Retrieve information about the specified file
     *
     * @param object $pathInfo
     * @return mixed
     */
    function getFile($pathInfo) {
        // If this is a file, only process if it's an image
        if(! $this->isImageFileExtension($pathInfo->extension)) {
            return null;
        }

        // Create a result object
        $result = new StdClass();
        // $result->path = $imagePath;
        $result->name = $pathInfo->filenameNoExt;
        $result->type = 'file';
        $result->imageUrl = $this->localFilePathToAlbumUrl($pathInfo->path);
        $result->filename = $pathInfo->filename;
        $result->modified = date('Y-m-d H:n:s', filemtime($pathInfo->path));

        $thumbnailPath = $this->getImageThumbnail($pathInfo, false);
        if($thumbnailPath) {
            $result->thumbnailUrl = $this->localFilePathToAlbumUrl($thumbnailPath);
        }

        return $result;        
    }

    /**
     * Return true if the file extension represents an image file
     *
     * @param string $ext
     * @return boolean
     */
    function isImageFileExtension($ext) {
        return \in_array(strtolower($ext), $this->supportedFileExts);
    }

    /**
     * Merge in data from the information file into $result, if it exists
     *
     * @param [type] $filename
     * @param [type] $isDirectory
     * @param [type] $result
     * @return void
     */
    function mergeInfo($infoFileName, &$result) {
        if(is_file($infoFileName)) {
            $lines = file($infoFileName);
            foreach($lines as $line) {
                $s = trim($line);
                if(strlen($s) > 0) {
                    $result->name = $s;
                }
            }
        }
    }
}