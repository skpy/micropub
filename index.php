<?php
require 'common.php';

$target_dir = getcwd() . '/' . $_SERVER['HTTP_HOST'];
check_target_dir($target_dir);

// GET Requests:- config, syndicate to & source
if (isset($_GET['q'])) {
  what_can_i_do();
}

// Take headers and other incoming data
$headers = getallheaders();
if ($headers === false ) {
  quit('invalid_headers', 'The request lacks valid headers', '400');
}
$headers = array_change_key_case($headers, CASE_LOWER);
$data = array();
if (!empty($_POST['access_token'])) {
  $token = "Bearer ".$_POST['access_token'];
  $headers["authorization"] = $token;
}

if (! isset($headers['authorization']) ) {
  quit('no_auth', 'No authorization token supplied.', 400);
}
// check the token for this connection.
indieAuth($headers['authorization'], $_SERVER['HTTP_HOST']);

// Are we getting a form-encoded submission?
if (isset($_POST['h'])) {
  $h = $_POST['h'];
  unset($_POST['h']);
  // create an object containing all the POST fields
  $data = [
    'type' => ['h-'.$h],
    'properties' => array_map(
      function ($a) {
        return is_array($a) ? $a : [$a];
      }, $_POST
    )
  ];
} else {
  // nope, we're getting JSON, so decode it.
  $data = json_decode(file_get_contents('php://input'), true);
}

if (empty($data)) {
  quit('no_content', 'No content', '400');
}

if (empty($data['properties']['content']['0'])
  && empty($data['properties']['photo']['0']) ) {
  // If this is a POST and there's no content or photo, exit
  if (empty($data['action'])) {
    quit('missing_content', 'Missing content.', '400');
  }
}

if ( empty($data['properties']['mp-slug'][0]) ) {
  $title = date('YmdHi');
} else {
  $title = trim( $data['properties']['mp-slug'][0] );
}
$slug = strtolower( preg_replace("/[^-\w+]/", "", str_replace(' ', '-', $title) ) );
$image_link = $data['properties']['photo']['0'];

// Build up the post file
$post = "---\n";
$post .= "title: $title \n";
$post .= 'date: ' . date('Y-m-d H:i:s') . "\n";
$post .= "permalink: $slug\n";
$post .= "twitterimage: $image_link\n";
$post .= "---\n";
$post .= $data['properties']['content']['0'] . "\n";

$markdown = './' . $_SERVER['HTTP_HOST'] . '/' . $slug . '.md';
$fh = fopen( $markdown, 'w' );
if ( ! $fh = fopen( $markdown, 'w' ) ) {
  quit('file_error', 'Unable to open Markdown file'. 400);
}
if ( fwrite($fh, $post ) === FALSE ) {
  quit('write_error', 'Unable to write Markdown file', 400);
}
fwrite($fh, $post);
fclose($fh);
chmod($file, 0777);
# sleep for 2 seconds, to allow incron to copy/move the file, and invoke Hugo
sleep(2);

header('HTTP/1.1 201 Created');
header('Location: https://' . $_SERVER['HTTP_HOST'] . '/' . $slug . '/');
?>
