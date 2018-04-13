<?php

use Symfony\Component\Yaml\Yaml;

function get_source_from_url($url) {
    global $config;
    //$part = substr($url, 0, strlen($config['base_url']));
    $part = str_replace($config['base_url'], $config['source_path'], $url);
    return str_replace('/index.html', '.md', $part);
}

function parse_file($original) {
    $properties = [];
    # all of the front matter will be in $parts[1]
    # and the contents will be in $parts[2]
    $parts = preg_split('/[\n]*[-]{3}[\n]/', file_get_contents($original), 3);
    $front_matter = Yaml::parse($parts[1]);
    // All values in mf2 json are arrays
    foreach (Yaml::parse($parts[1]) as $k => $v) {
        if(!is_array($v)) {
            $v = [$v];
        } 
        $properties[$k] = $v;
    }
    $content = trim($parts[2]);
    return ['properties' => $properties, 'content' => $content ];
}

# this function fetches the source of a post and returns a JSON
# encoded object of it.
function show_content_source($url) {
    header( "Content-Type: application/json");
    print json_encode( parse_file( get_source_from_url($url) ) );
}

?>
