<?php
namespace SimpleImageFileGallery;

use PHPUnit\Framework\TestCase;
use Exception;
use StdClass;

/**
 * Album processing class unit tests
 */
final class AlbumProcessorTest extends TestCase {
    
    protected $tmpDir;
    protected $processor;

    function setUp() {
        $this->tmpDir = sys_get_temp_dir();
        $this->processor = new AlbumProcessor(200, 200, '/', $this->tmpDir, ['jpg', 'png', 'bmp']);
    }

    private function setUpTestDir($name) {
        $dir = $this->tmpDir . DIRECTORY_SEPARATOR . $name;
        if(! file_exists($dir)) mkdir($dir);
        return $dir;
    }

    private function cleanUpTestDir($dir) {
        foreach(scandir($dir) as $item) {
            if($item == '.' || $item == '..') continue;
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if(is_dir($path)) {
                $this->cleanUpTestDir($path);
            } else if(is_file($path)) {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    private function addValidFile($dir, $name) {
        $file = $dir . DIRECTORY_SEPARATOR . $name;
        copy('webserviceTests/_test.png', $file);
        return $file;
    }

    private function addSubDirectory($dir, $name) {
        $subDir = $dir . DIRECTORY_SEPARATOR . $name;
        if(! file_exists($subDir)) mkdir($subDir);
        return $subDir;
    }

    private function pathToUrl($path) {
        $url = $path;
        $i = strpos($url, $this->tmpDir);
        if(($i !== FALSE) && ($i == 0)) {
            $url = substr($url, strlen($this->tmpDir));
        }
        $url = str_replace(DIRECTORY_SEPARATOR, '/', $url);
        if(is_dir($path)) {
            if(strlen($url) > 0) {
                if($url[strlen($url - 1)] != '/') {
                    $url .= '/';
                }
            }
        }
        return $url;
    }

    function testConstructorWithTrailingSlash() {
        $foo = sys_get_temp_dir() . '/';
        $this->assertNotEquals(null, new AlbumProcessor(200, 200, '#000', '/', $foo));
    }


    function testUrlToLocalPathInfoValidFile() {
        $dirName = 'testUrlToLocalPathInfoValidFile';
        $dir = $this->setUpTestDir($dirName);
        $file = $this->addValidFile($dir, 'test.png');
        try {
            $info = $this->processor->urlToLocalPathInfo("/$dirName/test.png");
            $this->assertEquals($file, $info->path);
            $this->assertEquals('test.png', $info->filename);
            $this->assertEquals('test', $info->filenameNoExt);
            $this->assertEquals('png', $info->extension);
            $this->assertEquals($dir, $info->parentDirectory);
            $this->assertEquals(false, $info->isDirectory);
        } finally {
            $this->cleanUpTestDir($dir);
        }
    }

    function testUrlToLocalPathInfoValidFolder() {
        $dirName = 'testUrlToLocalPathInfoValidFolder';
        $subDirName = 'sub1';
        $dir = $this->setUpTestDir($dirName);
        $subDir = $this->addSubDirectory($dir, $subDirName);
        try {
            $info = $this->processor->urlToLocalPathInfo("/$dirName/$subDirName");
            $this->assertEquals($subDir, $info->path);
            $this->assertEquals($subDirName, $info->filename);
            $this->assertEquals($subDirName, $info->filenameNoExt);
            $this->assertEquals('', $info->extension);
            $this->assertEquals($dir, $info->parentDirectory);
            $this->assertEquals(true, $info->isDirectory);
        } finally {
            $this->cleanUpTestDir($dir);
        }
    }

    function testLocalPathToPathInfo() {
        $dirName = 'testLocalPathToPadirNamethInfo.foo';
        $dir = $this->setUpTestDir($dirName);
        try {
            $info = $this->processor->localPathToPathInfo($dir);
            $this->assertEquals($dir, $info->path);
            $this->assertEquals($dirName, $info->filename);
            $this->assertEquals('testLocalPathToPadirNamethInfo', $info->filenameNoExt);
            $this->assertEquals('foo', $info->extension);
            $this->assertEquals('/tmp', $info->parentDirectory);
            $this->assertEquals(true, $info->isDirectory);
        } finally {
            rmdir($dir);
        }
    }

    function testLocalPathToPathInfoEmpty() {
        $info = $this->processor->localPathToPathInfo('');
        $this->assertEquals(null, $info);
        $info = $this->processor->localPathToPathInfo(null);
        $this->assertEquals(null, $info);
    }

    function testLocalPathToDataUrl() {
        $this->assertEquals('/abc/', $this->processor->localPathToDataUrl('/tmp/abc'));
        $this->assertEquals('/abc/', $this->processor->localPathToDataUrl('/tmp/abc/'));
        $this->assertEquals('/abc/foo.txt', $this->processor->localPathToDataUrl('/tmp/abc', 'foo.txt'));
    }

    function testLocalFilePathToAlbumUrl() {
        $this->assertEquals('/abc', $this->processor->localFilePathToAlbumUrl('abc'));
        $this->assertEquals('/abc', $this->processor->localFilePathToAlbumUrl('/tmp/abc'));
    }

    function testForceTrailingSlash() {
        $this->assertEquals('/', $this->processor->forceTrailingSlash(''));
        $this->assertEquals('foo/', $this->processor->forceTrailingSlash('foo'));
        $this->assertEquals('foo/', $this->processor->forceTrailingSlash('foo/'));
    }

    function testGetImageThumbnailBadExtension() {
        $dirName = 'testGetImageThumbnailBadExtension';
        $dir = $this->setUpTestDir($dirName);
        $file = $this->addValidFile($dir, 'test.bad');
        try {
            $info = $this->processor->localPathToPathInfo($file);
            $result = $this->processor->getImageThumbnail($info);
            $this->assertEquals(null, $result);
        } finally {
            $this->cleanUpTestDir($dir);
        }
    }

    function testGetImageThumbnailNoForce() {
        $dirName = 'testGetImageThumbnailNoForce';
        $dir = $this->setUpTestDir($dirName);
        try {
            $testNoExist = $dir . DIRECTORY_SEPARATOR . 'test.jpg';
            $info = $this->processor->localPathToPathInfo($testNoExist);
            $result = $this->processor->getImageThumbnail($info);
            $this->assertEquals(null, $result);
        } finally {
            $this->cleanUpTestDir($dir);
        }
    }

    function testGetImageThumbnailInvalidImage() {
        $dirName = 'testGetImageThumbnailInvalidImage';
        $dir = $this->setUpTestDir($dirName);
        $file = $this->addValidFile($dir, 'test.jpg');
        file_put_contents($file, 'FOO123');
        try {
            $testBad = $dir . DIRECTORY_SEPARATOR . 'test.jpg';
            $info = $this->processor->localPathToPathInfo($testBad);
            $result = $this->processor->getImageThumbnail($info, TRUE);
            $this->assertEquals(null, $result);
        } finally {
            $this->cleanUpTestDir($dir);
        }
    }

    function testGetImageThumbnailValidFileImage() {
        $dirName = 'testGetImageThumbnailValidFileImage';
        $dir = $this->setUpTestDir($dirName);
        $file = $this->addValidFile($dir, 'test.jpg');
        $thumbnail = $dir . DIRECTORY_SEPARATOR . 'thumbnails' . DIRECTORY_SEPARATOR . 'test-thumbnail.jpg';
        try {
            $testValid = $dir . DIRECTORY_SEPARATOR . 'test.jpg';
            $info = $this->processor->localPathToPathInfo($testValid);
            $result = $this->processor->getImageThumbnail($info, true);
            $this->assertEquals($thumbnail, $result);
        } finally {
            $this->cleanUpTestDir($dir);
        }
    }

    function testGetImageThumbnailDirectory() {
        $dirName = 'testGetImageThumbnailDirectory';
        $dir = $this->setUpTestDir($dirName);
        $url = $this->pathToUrl($dir);
        $thumbnailUrl = $url . 'thumbnails/directory.jpg';
        $subdir = $this->addSubDirectory($dir, 'sub1');
        $imageFile = $this->addValidFile($subdir, 'test.png');
        try {
            $result = $this->processor->getThumbnail($url);
            $this->assertEquals($thumbnailUrl, $result->thumbnailUrl);
        } finally {
            $this->cleanUpTestDir($dir);
        }
    }

    function testGetImageThumbnailDirectoryExisting() {
        $dirName = 'testGetImageThumbnailDirectoryExisting';
        $dir = $this->setUpTestDir($dirName);
        $url = $this->pathToUrl($dir);
        $thumbnailUrl = $url . 'thumbnails/directory.jpg';
        $subdir = $this->addSubDirectory($dir, 'sub1');
        $imageFile = $this->addValidFile($subdir, 'test.png');
        try {
            // Make the thumbnail
            $this->processor->getThumbnail($url);
            // Get existing
            $result = $this->processor->getThumbnail($url);
            $this->assertEquals($thumbnailUrl, $result->thumbnailUrl);
        } finally {
            $this->cleanUpTestDir($dir);
        }
    }

    function testGetDirectoryThumbnailNoExistNoForce() {
        $dirName = 'testGetDirectoryThumbnailNoExistNoForce';
        $dir = $this->setUpTestDir($dirName);
        $imageFile = $this->addValidFile($subdir, 'test.png');
        try {
            $info = $this->processor->localPathToPathInfo($dir);
            $result = $this->processor->getDirectoryThumbnail($info, false);
            $this->assertEquals(null, $result);
        } finally {
            $this->cleanUpTestDir($dir);
        }
    }

    function testGetDirectoryThumbnailWithFile() {
        $dirName = 'testGetDirectoryThumbnailWithFile';
        $dir = $this->setUpTestDir($dirName);
        $imageFile = $this->addValidFile($dir, 'test.png');
        try {
            $info = $this->processor->localPathToPathInfo($imageFile);
            unlink($imageFile);
            $result = $this->processor->getDirectoryThumbnail($info, false);
            $this->assertEquals(null, $result);
        } finally {
            $this->cleanUpTestDir($dir);
        }
    }

    function testGetImageThumbnailFile() {
        $dirName = 'testGetImageThumbnailFile';
        $dir = $this->setUpTestDir($dirName);
        $url = $this->pathToUrl($dir);
        $imageFile = $this->addValidFile($dir, 'test.png');
        $imageUrl = $this->pathToUrl($imageFile);
        $thumbnailUrl = $url . 'thumbnails/test-thumbnail.jpg';

        try {
            $result = $this->processor->getThumbnail($imageUrl);
            $this->assertEquals($thumbnailUrl, $result->thumbnailUrl);
        } finally {
            $this->cleanUpTestDir($dir);
        }
    }

    function testGetImageThumbnailFileExisting() {
        $dirName = 'testGetImageThumbnailFileExisting';
        $dir = $this->setUpTestDir($dirName);
        $url = $this->pathToUrl($dir);
        $imageFile = $this->addValidFile($dir, 'test.png');
        $imageUrl = $this->pathToUrl($imageFile);
        $thumbnailUrl = $url . 'thumbnails/test-thumbnail.jpg';

        try {
            $this->processor->getThumbnail($imageUrl);
            $result = $this->processor->getThumbnail($imageUrl);
            $this->assertEquals($thumbnailUrl, $result->thumbnailUrl);
        } finally {
            $this->cleanUpTestDir($dir);
        }
    }

    function testGetImageThumbnailNoExistNoCreate() {
        $dirName = 'testGetImageThumbnailNoExistNoCreate';
        $dir = $this->setUpTestDir($dirName);
        $imageFile = $this->addValidFile($dir, 'test.png');
        try {
            $info = $this->processor->localPathToPathInfo($imageFile);
            unlink($imageFile);
            $result = $this->processor->getImageThumbnail($info, false);
            $this->assertEquals(null, $result);
        } finally {
            $this->cleanUpTestDir($dir);
        }
    }

    function testGetImageThumbnailInvalidUrl() {
        $this->expectException(\InvalidArgumentException::class);
        $result = $this->processor->getThumbnail('/bogus/');
    }

    function testGetDirectoryNoParam() {
        $result = $this->processor->getDirectory(null, false);
        $this->assertEquals(null, $result);
    }

    function testGetDirectoryWithFile() {
        $dir = $this->setUpTestDir('testGetDirectoryWithFile');
        $imageFile = $this->addValidFile($dir, 'image.png');
        try {
            $info = $this->processor->localPathToPathInfo($imageFile);
            $this->expectException(Exception::class);
            $this->processor->getDirectory($info, false);
        } finally {
            $this->cleanUpTestDir($dir);
        }
    }

    function testGetDirectoryIgnoreThumbnails() {
        $dir = $this->setUpTestDir('testGetDirectoryIgnoreThumbnails');
        $subdir = $this->addSubDirectory($dir, 'thumbnails');
        try {
            $info = $this->processor->localPathToPathInfo($subdir);
            $result = $this->processor->getDirectory($info, false);
            $this->assertEquals(null, $result);
        } finally {
            $this->cleanUpTestDir($dir);
        }
    }

    function testGetDirectoryDescending() {
        $dir = $this->setUpTestDir('testGetDirectoryDescending');
        $url = $this->pathToUrl($dir);
        $thumbnailUrl = $url . 'thumbnails/directory.jpg';
        $subdir = $this->addSubDirectory($dir, 'sub1');
        $imageFile = $this->addValidFile($dir, 'test.png');
        $imageUrl = $this->pathToUrl($imageFile);

        try {
            $info = $this->processor->localPathToPathInfo($dir);

            // Create thumbnail to perculate up
            $imageInfo = $this->processor->localPathToPathInfo($imageFile);
            $this->processor->getImageThumbnail($imageInfo, true);
            $this->processor->getDirectoryThumbnail($info, true);

            $result = $this->processor->getDirectory($info, true);

            $this->assertEquals('testGetDirectoryDescending', $result->name);
            $this->assertEquals('directory', $result->type);
            $this->assertEquals($url, $result->dataUrl);
            $this->assertEquals($thumbnailUrl, $result->thumbnailUrl);
            $this->assertEquals(2, count($result->children));
            $resultChild = $result->children[0];
            $this->assertEquals('sub1', $resultChild->name);
            $this->assertEquals('directory', $resultChild->type);
            $this->assertEquals($url . 'sub1/', $resultChild->dataUrl);
            $this->assertEquals(null, $resultChild->thumbnailUrl);
            $resultFile = $result->children[1];
            $this->assertEquals('test', $resultFile->name);
            $this->assertEquals('test.png', $resultFile->filename);
            $this->assertEquals('file', $resultFile->type);
            $this->assertEquals($imageUrl, $resultFile->imageUrl);
            $this->assertEquals($url . 'thumbnails/test-thumbnail.jpg', $resultFile->thumbnailUrl);
        } finally {
            $this->cleanUpTestDir($dir);
        }
    }

    function testGetFileBadExt() {
        $tmpFileName = $this->tmpDir . DIRECTORY_SEPARATOR . 'testGetFile.bad';
        \file_put_contents($tmpFileName, 'testing');
        try {
            $info = $this->processor->urlToLocalPathInfo('/testGetFile.bad');
            $result = $this->processor->getFile($info);
            $this->assertEquals(null, $result);
        } finally {
            unlink($tmpFileName);
        }
    }

    function testGetFileValid() {
        $dirName = 'testGetFileValid';
        $dir = $this->setUpTestDir($dirName);
        $imageFile = $this->addValidFile($dir, 'test.png');
        $thumbnailUrl = $this->pathToUrl($dir) . 'thumbnails/test-thumbnail.jpg';
        try {
            $info = $this->processor->localPathToPathInfo($imageFile);
            $thumbnailResult = $this->processor->getImageThumbnail($info, true);
            $result = $this->processor->getFile($info);
            $this->assertEquals('test', $result->name);
            $this->assertEquals('test.png', $result->filename);
            $this->assertEquals('file', $result->type);
            $this->assertEquals($thumbnailUrl, $result->thumbnailUrl);
        } finally {
            $this->cleanUpTestDir($dir);
        }
    }

    function testIsFileExtension() {
        $this->assertEquals(true, $this->processor->isImageFileExtension('png'));
        $this->assertEquals(true, $this->processor->isImageFileExtension('jpg'));
        $this->assertEquals(false, $this->processor->isImageFileExtension('txt'));
    }

    function testMergeInfo() {
        $tmpHandle = tmpfile();
        $tmpFileName = stream_get_meta_data($tmpHandle)['uri'];
        fwrite($tmpHandle, 'Test #1');
        try {
            $result = new StdClass();
            $this->processor->mergeInfo($tmpFileName, $result);
            $this->assertEquals('Test #1', $result->name);
        } finally {
            fclose($tmpHandle);
        }
    }

    function testFindBadPath() {
        $result = $this->processor->find('/foo');
        $this->assertEquals(null, $result);
    }

    function testFindHierarchy() {
        $dir = $this->setUpTestDir('testFindHierarchy');
        $subdir1 = $this->addSubDirectory($dir, 'sub1');
        $subdir2 = $this->addSubDirectory($subdir1, 'sub2');
        $image1 = $this->addValidFile($subdir1, 'image1.png');
        $image1 = $this->addValidFile($subdir1, 'image2.png');

        try {
            $result = $this->processor->find($this->pathToUrl($subdir1));
            $this->assertEquals('sub1', $result->name);
        } finally {
            $this->cleanUpTestDir($dir);
        }
    }

    function testFindFile() {
        $dir = $this->setUpTestDir('testFindHierarchy');
        $subdir1 = $this->addSubDirectory($dir, 'sub1');
        $image1 = $this->addValidFile($subdir1, 'image1.png');

        try {
            $result = $this->processor->find($this->pathToUrl($image1));
            $this->assertEquals('image1', $result->name);
            $this->assertEquals('image1.png', $result->filename);
            $this->assertEquals('file', $result->type);
            $this->assertEquals($this->pathToUrl($image1), $result->imageUrl);
        } finally {
            $this->cleanUpTestDir($dir);
        }
    }
}