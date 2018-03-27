<?php
require 'common.php';

$base_url = 'https://' . $_SERVER['HTTP_HOST'] . '/images/';
$target_dir = getcwd() . '/' . $_SERVER['HTTP_HOST'];
$max_width = 800;

check_target_dir($target_dir);
indieAuth($_SERVER['HTTP_AUTHORIZATION']);

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Authorization');
header('Content-Type: application/json');

// Check for a file
if(!array_key_exists('file', $_FILES)) {
  quit('invalid_request', 'The request must have a file upload named "file"', '400');
}

$file = $_FILES['file'];

# first make sure the file isn't too large
if ( $file['size'] > 6000000 )  {
  quit('too_large', 'The file is too large.', '413');
}
# now make sure it's an image. We only deal with JPG, GIF, PNG right now
$finfo = new finfo(FILEINFO_MIME_TYPE);
if (false === $ext = array_search(
  $finfo->file($file['tmp_name']),
  array(
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'gif'  => 'image/gif',
  ),
  true
)) {
  quit('invalid_file', 'Invalid file type was uploaded.', '415');
}

# extra caution to ensure the file doesn't already exist
$ext = strtolower(pathinfo(basename($file['name']), PATHINFO_EXTENSION));
$filename = date('YmdHis') . ".$ext";
if ( file_exists("$target_dir/$filename")) {
  quit('file_exists', 'A filename conflict has occurred on the server.', '409');
}

# we got here, so let's copy the file into place.
if (! move_uploaded_file($file["tmp_name"], "$target_dir/$filename")) {
  quit('file_error', 'Unable to save the uploaded file', '403');
}

// now resize the image
$file = "$target_dir/$filename";
switch( $ext ) {
  case 'jpg':
  case 'jpeg':
    $im = imagecreatefromjpeg($file);
    break;
  case 'gif':
    $im = imagecreatefromgif($file);
    break;
  case 'png':
    $im = imagecreatefrompng($file);
  default:
    # somehow we got an invalid file extension
    quit('invalid_file', 'Invalid file type.', '415');
}
if ( imagesx( $im ) > $max_width ) {
  $im = imagescale( $im, $max_width );
}
switch( $ext ) {
  case 'jpg':
  case 'jpeg':
    $result = imagejpeg( $im, $file);
    break;
  case 'gif':
    $result = imagegif( $im, $file );
    break;
  case 'png':
    $result = imagepng( $im, $file );
  default:
    # somehow we got an invalid file extension?
    quit('invalid_file', 'Invalid file type.', '415');
}
// Free up memory
imagedestroy($im);
chmod($file, 0777);

$url = $base_url . $filename;
header('HTTP/1.1 201 Created');
header('Location: ' . $url);

echo json_encode([ 'url' => $url ]);
?>
