<?php

# this syndication target is for testing and debug purposes.
# it will simply create a file in the specified directory with the
# values of the post that is being syndicated.

function syndicate_debug($config, $properties, $content, $url) {

    $debug_path = $config['base_path'] . 'public/' . $config['syndication']['debug']['path'];
    if ( ! file_exists($debug_path)) {
        if ( FALSE === mkdir($debug_path, 0777, true) ) {
            quit(400, 'cannot_mkdir', 'The content directory could not be created.');
        }
    }
    $fileanme = date('YmdHi') . '.txt';

    $data = "-----DEBUG OUTPUT-----\n";
    $data .= var_export($properties, true);
    $data .= "\n----------\n";
    $data .= var_export($content, true);
    $data .= "\n----------\n";
    $data .= $url;

    if ( FALSE === file_put_contents( $debug_path . $filename, $data ) ) {
        quit(400, 'file_error', 'Unable to open debug file');
    }
    return $config['base_url'] . $config['syndication']['debug']['path'] . $filename;
}
