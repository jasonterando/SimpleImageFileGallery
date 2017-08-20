<?php
namespace SimpleImageFileGallery;

require_once 'src/Configuration.php';
require_once 'src/AlbumProcessor.php';
require_once 'src/Router.php';

# Change to the index.php's file location, which makes it easier to deal with relative paths
chdir(dirname(__FILE__));

$config = new Configuration('config.json');

$processor = new AlbumProcessor(
    $config->get('thumbnailMaxWidth'),
    $config->get('thumbnailMaxHeight'),
    $config->get('imageURL'),
    $config->get('imagePath'),
    $config->get('supportedFileExts')
    );

(new Router($processor))->Dispatch(array_key_exists('cmd', $_REQUEST) ? $_REQUEST['cmd'] : null);