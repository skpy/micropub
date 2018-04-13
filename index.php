<?php
$plugins = array();

# we can't do anything without a config file
if ( ! file_exists('config.php') ) {
    die;
}
$config = include_once './config.php';
# invoke the composer autoloader for our dependencies
require_once __DIR__.'/vendor/autoload.php';
use Symfony\Component\Yaml\Yaml;

# load our common libraries
include_once './inc/common.php';
include_once './inc/content.php';
#require_once './inc/plugin.php';

# is this a GET request?
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['q'])) {
        switch ($_GET['q']):
            case 'config':
                show_config();
                break;
            case 'source':
               show_content_source($_GET['url']);
                break;
            case 'syndicate-to':
                show_config('syndicate-to');
                break;
            default:
                show_info();
                break;
        endswitch;
    } else {
        show_info();
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (array_key_exists('file', $_FILES)) {
        # this appears to be a file upload. Handle it.
        include_once('./inc/media.php');
        media_upload();
    } else {
        switch($_POST['action']):
            case 'delete':
                delete($_POST['url']);
                break;
            case 'undelete':
                undelete($_POST['url']);
                break;
            case 'update':
                update($_POST['url']);
                break;
            default:
                create();
                break;
        endswitch;
    }
} else {
    # something other than GET or POST?  Unsupported.
    quit(400, 'invalid_request', 'HTTP method unsupported');
}
?>
