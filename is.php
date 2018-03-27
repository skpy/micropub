<?php
/* custom upload script for hugo powered site
 *
 * this script accepts an HTTP POST of an image, and some text, and composes
 * a Markdown file for use by Hugo.
 *
 * My Hugo powered sites are all in /var/www/<domain>.
 * I run PHP in a container which only has access to /var/www/html.
 * Because my PHP container has limited filesystem access, this script
 * creates the files in a sub-directory of the current directory, based
 * on the calling site's name (thus allowing a single script to work
 * for multiple distinct domain invocations).
 *
 * incrontab (http://inotify.aiken.cz/?section=incron&page=about&lang=en)
 * is used to watch these directories, and execute a script on newly created 
 * files, copying them into the appropriate destination directories.
 * Because incrontab is running on the host, outside of Docker, it has full 
 * access to the host filesystem. The script triggered by incrontab also
 * executes Hugo to rebuild the site when new Markdown files are discovered.
 *
**/

require 'common.php';

if ( ! file_exists('./secret.txt')) {
  quit('error', 'Missing security information', 400);
}
$secret = trim(file_get_contents('./secret.txt'));
if ( FALSE === $secret ) {
  quit('error', 'Missing security information', 400);
}

$base_url = 'https://' . $_SERVER['HTTP_HOST'];
$target_dir = getcwd() . '/' . $_SERVER['HTTP_HOST'];
$max_width = 800;

# make sure our upload directory exists
check_target_dir($target_dir);

if ( ! isset($_POST['secret']) || $_POST['secret'] != $secret ) {
  quit('no_auth', 'Not authenticated', 400);
}

if ( empty($_POST['title'])) { quit('no_title', 'No title supplied.', 400); }

$title = trim( $_POST['title'] );
$slug = strtolower( preg_replace("/[^-\w+]/", "", str_replace(' ', '-', $title) ) );
$description = trim( $_POST['description'] );
$content = trim( $_POST['content'] );

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
$filename = "$slug.$ext";
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
$image_url = $base_url . '/images/' . $filename;

# now create the Markdown file
$post = "---\n";
$post .= "title: $title \n";
$post .= "description: $description\n";
$post .= 'date: ' . date('Y-m-d H:i:s') . "\n";
$post .= "permalink: $slug\n";
$post .= "twitterimage: $image_url\n";
$post .= "---\n";
$post .= "![skippy is $title]($image_url)\n\n";
$post .= "$content\n";
$markdown = $target_dir . '/' . $slug . '.md';
if ( ! $fh = fopen( $markdown, 'w' ) ) {
  quit('file_error', 'Unable to open Markdown file'. 400);
}
if ( fwrite($fh, $post ) === FALSE ) {
  quit('write_error', 'Unable to write Markdown file', 400);
}
fclose($fh);

# sleep for 2 seconds, to ensure that incron has a chance to
# copy / move the files, and so Hugo can run to build the site
sleep(2);

#header('Access-Control-Allow-Origin: *');
#header('Access-Control-Allow-Headers: Authorization');
#header('Content-Type: application/json');
$url = $base_url . '/' . $slug . '/';
header('Location: ' . $url);
header('HTTP/1.1 200 OK');
echo '<h1><a href="' . $url . '">' . $url . '</a></h1>';
?>
