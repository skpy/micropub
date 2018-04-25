<?php

function check_target_dir($target_dir) {
    if ( empty($target_dir)) {
        quit(400, 'unknown_dir', 'Unspecified directory');
    }
    # make sure our upload directory exists
    if ( ! file_exists($target_dir) ) {
        # fail if we can't create the directory
        if ( FALSE === mkdir($target_dir, 0777, true) ) {
            quit(400, 'cannot_mkdir', 'The upload directory could not be created.');
        }
    }
}

function resize_image($file, $width) {
    # https://stackoverflow.com/a/25181208/3297734
    $ext = pathinfo(basename($file), PATHINFO_EXTENSION);
    $new = @imagecreatefromstring(@file_get_contents($file));
    // resize to our max width
    $new = imagescale( $new, $width );
    if ( $new === false ) {
        quit(415, 'invalid_file', 'Unable to process image.');
    }
    $width = imagesx($new);
    $height = imagesy($new);
    // create a new image from our source, for safety purposes
    $target = imagecreatetruecolor($width, $height);
    imagecopy($target, $new, 0, 0, 0, 0, $width, $height);
    imagedestroy($new);
    if ( $ext == 'gif' ) {
        // Convert to palette-based with no dithering and 255 colors
        imagetruecolortopalette($target, false, 255);
    }
    // write the file, using the GD function of the file type
    $result = call_user_func("image$ext", $target, $file);
    if ( $result === false ) {
        quit(400, 'error', 'unable to write image');
    }
    imagedestroy($target);
    chmod($file, 0744);
}

function media_upload($file, $target_dir, $max_width) {
    # first make sure the file isn't too large
    if ( $file['size'] > 6000000 )  {
        quit(413, 'too_large', 'The file is too large.');
    }
    # now make sure it's an image. We only deal with JPG, GIF, PNG right now
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    if (false === $ext = array_search($finfo->file($file['tmp_name']),
      array(
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
      ), true) ) {
        quit(415, 'invalid_file', 'Invalid file type was uploaded.');
    }

    $ext = strtolower(pathinfo(basename($file['name']), PATHINFO_EXTENSION));
    if ( $ext == 'jpg' ) {
        # normalize JPEG extension, so we can invoke GD functions easier
        $ext = 'jpeg';
    }
    # define our own name for this file.
    $orig = explode('.', $file['name'])[0];
    $date = new DateTime();
    $filename = $orig . '-' . $date->format('u') . ".$ext";
    # extra caution to ensure the file doesn't already exist
    if ( file_exists("$target_dir$filename")) {
        quit(409, 'file_exists', 'A filename conflict has occurred on the server.');
    }

    # we got here, so let's copy the file into place.
    if (! move_uploaded_file($file["tmp_name"], "$target_dir$filename")) {
        quit(403, 'file_error', 'Unable to save the uploaded file');
    }

    // check the image and resize if necessary
    $details = getimagesize("$target_dir$filename");
    if ( $details === false ) {
        quit(415, 'invalid_file', 'Invalid file type was uploaded.');
    }
    if ( $details[0] > $max_width ) {
        resize_image("$target_dir$filename", $max_width );
    }

    return $filename;
}
?>
