<?php
namespace SimpleImageFileGallery;

use Exception;
use InvalidArgumentException;

/**
 * Class to load configuration and provide configuration values
 */
class Configuration {
    private $config;

    /**
     * Initialize configuration using the specified file name
     *
     * @param [type] $configFileName
     */
    function __construct($configFileName) {
        $rawConfig = file_get_contents($configFileName);
        $this->config = json_decode($rawConfig);
        if(! $this->config) {
            throw new Exception("Invalid format for config.json");
        }
    }

    /**
     * Retrieve the specified value (or all values if $value is empty)
     *
     * @param [type] $value
     * @return void
     */
    function get($value = null) {
        if(isset($value)) {
            if(\property_exists($this->config, $value)) {
                return $this->config->$value;
            } else {
                throw new \InvalidArgumentException("$value is not a defined configuration option");
            }
        } else {
            return $this->config;
        }
    }
}