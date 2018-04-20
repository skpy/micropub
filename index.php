<?php

# we can't do anything without a config file
if ( ! file_exists('config.php') ) {
    die;
}
$config = include_once './config.php';
date_default_timezone_set($config['tz']);

# invoke the composer autoloader for our dependencies
require_once __DIR__.'/vendor/autoload.php';

# load our common libraries
include_once './inc/common.php';
include_once './inc/content.php';
include_once './inc/media.php';

// Take headers and other incoming data
$headers = getallheaders();
if ($headers === false ) {
    quit(400, 'invalid_headers', 'The request lacks valid headers');
}
$headers = array_change_key_case($headers, CASE_LOWER);
if (!empty($_POST['access_token'])) {
    $token = "Bearer ".$_POST['access_token'];
    $headers["authorization"] = $token;
}
if (! isset($headers['authorization']) ) {
    quit(401, 'no_auth', 'No authorization token supplied.');
}
// check the token for this connection.
indieAuth($headers['authorization'], $config['base_url']);

# is this a GET request?
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['q'])) {
        switch ($_GET['q']):
            case 'config':
                show_config();
                break;
            case 'source':
               show_content_source($_GET['url'], $_GET['properties']);
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
        #
        # NOTE: micropub media uploads expect the file to be immediately
        # available, so we upload to the site's directory, and optionally
        # copy to the source directory for inclusion with later site builds.
        # uploads will be stored at '/base/uploads/YYYY/mm/'
        $subdir = date('Y/m/');
        $upload_path = $config['base_path'] . $config['upload_path'] . $subdir;
        $copy_path = $config['source_path'] . 'static/' . $config['upload_path'] . $subdir;
        check_target_dir($upload_path);
        $upload = media_upload($upload_path, $config['max_image_width']);
        # do we need to copy this file to the source /static/ directory?
        if ($config['copy_uploads_to_source'] === TRUE ) {
            # we need to ensure '/source/statuc/uploads/YYYY/mm/' exists
            check_target_dir($copy_path);
            if ( copy ( $upload_path . $upload, $copy_path . $upload ) === FALSE ) {
                quit(400, 'copy_error', 'Unable to copy upload to source directory');
            }
        }
        $url = $config['base_url'] . $config['upload_path'] . $subdir . $upload;
        header('HTTP/1.1 201 Created');
        header('Location: ' . $url);
        echo json_encode([ 'url' => $url ]); 
        die();
    } else {
        # not a file upload. Parse the JSON or POST body into an object
        $request = parse_request();
        switch($request->action):
            case 'delete':
                delete($request);
                break;
            case 'undelete':
                undelete($request);
                break;
            case 'update':
                update($request);
                break;
            default:
                create($request);
                break;
        endswitch;
    }
} else {
    # something other than GET or POST?  Unsupported.
    quit(400, 'invalid_request', 'HTTP method unsupported');
}
?>
