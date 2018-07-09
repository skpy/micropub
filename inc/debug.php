<?php

# this syndication target is for testing and debug purposes.
# it will simply create a file in the specified directory with the
# values of the post that is being syndicated.

function syndicate_debug($config, $properties, $content, $url) {

    if ( ! file_exists($config['path'])) {
        if ( FALSE === mkdir($config['path'], 0777, true) ) {
            quit(400, 'cannot_mkdir', 'The debug directory could not be created.');
        }
    }
    $filename = date('YmdHi') . '.txt';

    $data = "-----DEBUG OUTPUT-----\n";
    $data .= var_export($properties, true);
    $data .= "\n----------\n";
    $data .= var_export($content, true);
    $data .= "\n----------\n";
    $data .= $url;

    if ( FALSE === file_put_contents( $config['path'] . $filename, $data ) ) {
        quit(400, 'file_error', 'Unable to open debug file');
    }
    return $config['url'] . $filename;
}
