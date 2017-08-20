<?php
namespace SimpleImageFileGallery;

use PHPUnit\Framework\TestCase;
use Exception;

/**
 * Configuration class unit tests
 */
final class ConfigurationTest extends TestCase {
    
    public function testValidConfiguration() {
        $tmpHandle = tmpfile();
        $tmpFileName = stream_get_meta_data($tmpHandle)['uri'];
        $payload = ['abc' => '123', 'def' => '456'];
        fwrite($tmpHandle, json_encode($payload));
        try {
            $config = new Configuration($tmpFileName);
            $this->assertEquals('123', $config->get('abc'));
            $this->assertEquals('456', $config->get('def'));
            $this->assertJsonStringEqualsJsonString(
                json_encode($payload), 
                json_encode($config->get())
            );
            $this->expectException(\InvalidArgumentException::class);
            $config->get('bad');
       } finally {
            fclose($tmpHandle);
        }
    }

    public function testInvalidConfiguration() {
        $tmpHandle = tmpfile();
        $tmpFileName = stream_get_meta_data($tmpHandle)['uri'];
        fwrite($tmpHandle, '{"Broken:');
        try {
            $this->expectException(\Exception::class);
            new Configuration($tmpFileName);
       } finally {
            fclose($tmpHandle);
        }
    }
    
    public function testMissingConfiguration() {
        $this->expectException(\PHPUnit\Framework\Error\Error::class);
        new Configuration('/tmp/foo');
    }
}