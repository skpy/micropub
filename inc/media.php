<?php

function resize_image($file, $width) {
    # https://stackoverflow.com/a/25181208/3297734
    $rotate = 0;
    $ext = pathinfo(basename($file), PATHINFO_EXTENSION);
    # if this is a JPEG, read the Exif data in order to
    # rotate the image, if needed
    if ($ext == 'jpeg') {
        $exif = @exif_read_data($file);
        if (!empty($exif['Orientation'])) {
            switch ($exif['Orientation']) {
                case 3:
                    $rotate = 180;
                    break;
                case 6:
                     $rotate = -90;
                     break;
                case 8:
                     $rotate = 90;
                     break;
            }
        }
    }
    $new = @imagecreatefromstring(@file_get_contents($file));
    if ($rotate !== 0) {
        $new = imagerotate($new, $rotate, 0);
    }
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
    chmod($file, 0644);
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
    # and replace spaces with dashes, for sanity and safety
    $orig = str_replace(' ', '-', explode('.', $file['name'])[0]);
    $date = new DateTime();
    $filename = $orig . '-' . $date->format('u') . "-$max_width.$ext";
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

    # let's make a thumbnail, too.
    $thumbnail = str_replace("-$max_width.$ext", "-200.$ext", $filename);
    copy("$target_dir$filename", "$target_dir$thumbnail");
    resize_image("$target_dir$thumbnail", 200 );

    return $filename;
}
?>
