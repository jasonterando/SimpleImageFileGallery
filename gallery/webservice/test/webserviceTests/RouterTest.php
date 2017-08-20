<?php
namespace SimpleImageFileGallery;

use PHPUnit\Framework\TestCase;
use Exception;

/**
 * Route class unit tests
 * @runTestsInSeparateProcesses
 * Note - we need the runTestsInSeparateProcesses to keep PHPUnit from preventing header writes
 */
final class RouterTest extends TestCase {
    
    protected $mockProcessor;
    protected $router;

    public function setUp() {
        $this->mockProcessor = $this->getMockbuilder(AlbumProcessor::class)->disableOriginalConstructor()->getMock();
        $this->router = new Router($this->mockProcessor);
    }
    
    public function testInvalidCommand() {
        $this->router->dispatch('/abc/');
        $this->assertEquals(404, http_response_code());
        $this->assertJsonStringEqualsJsonString(
            json_encode(['ErrorMessage' => 'Invalid Request']),
            $this->getActualOutput()
        );
    }

    public function testFailedCommand() {
        $this->mockProcessor->expects($this->once())
             ->method('find')
             ->will($this->throwException(new Exception('Argh!')));
    
        $this->router->dispatch('info/bad12345/');
        $this->assertEquals(400, http_response_code());
        $this->assertJsonStringEqualsJsonString(
            json_encode(['ErrorMessage' => 'Argh!']), 
            $this->getActualOutput()
        );
    }

    public function testInfoCommand() {
        $result = ['foo' => 'bar'];
        $this->mockProcessor->expects($this->once())
             ->method('find')
             ->with('test12345/')
             ->will($this->returnValue($result));
    
        $this->router->dispatch('info/test12345/');
        $this->assertEquals(200, http_response_code());
        $this->assertJsonStringEqualsJsonString(
            json_encode($result), 
            $this->getActualOutput()
        );
    }

    public function testThumbnailCommand() {
        $result = ['thumbnailUrl' => 'foo/foo'];
        $this->mockProcessor->expects($this->once())
             ->method('getThumbnail')
             ->with('image12345/')
             ->will($this->returnValue($result));
    
        $this->router->dispatch('thumbnail/image12345/');
        $this->assertEquals(200, http_response_code());
        $this->assertJsonStringEqualsJsonString(
            json_encode($result), 
            $this->getActualOutput()
        );
    }
}