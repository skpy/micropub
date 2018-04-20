<?php

use Symfony\Component\Yaml\Yaml;

function get_source_from_url($url) {
    global $config;

    # our config has the Hugo root, so append "content/".
    $source_path = $config['source_path'] . 'content/';
    $path = str_replace($config['base_url'], $source_path, $url);
    if ('index.html' == substr($path, -10)) {
        # if this was a full URL to "/index.html", replace that with ".md"
        $path = str_replace('/index.html', '.md', $path);
    } elseif ( '/' == substr($path, -1)) {
        # if this is a URL ending in just "/", replace that with ".md"
        $path = rtrim($path, '/') . '.md';
    } else {
        # should be a URL of the directory containing index.htm, so just
        # tack on ".md" to the path
        $path .= '.md';
    }
    return $path;
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
    $properties['content'] = [ trim($parts[2]) ];
    return $properties;
}

# this function fetches the source of a post and returns a JSON
# encoded object of it.
function show_content_source($url, $properties = []) {
    $source = parse_file( get_source_from_url($url) );
    $props = [];

    # the request may define specific properties to return, so
    # check for them.
    if ( ! empty($properties)) {
        foreach ($properties as $p) {
            if (array_key_exists($p, $source)) {
                $props[$p] = $source[$p];
            }
        }
    } else {
        $props = parse_file( get_source_from_url($url) );
    }
    header( "Content-Type: application/json");
    print json_encode( [ 'properties' => $props ] );
    die();
}

# this takes a string and returns a slug.
# I generally don't use non-ASCII items in titles, so this doesn't
# worry about any of that.
function slugify($string) {
    return strtolower( preg_replace("/[^-\w+]/", "", str_replace(' ', '-', $string) ) );
}

# this takes an MF2 array of arrays and converts single-element arrays
# into non-arrays.
function normalize_properties($properties) {
    $props = [];
    foreach ($properties as $k => $v) {
        if (is_array($v) && count($v) === 1) {
            $props[$k] = $v[0];
        } else {
            $props[$k] = $v;
        }
    }
    # MF2 defines "name" instead of title, but Hugo wants "title"
    $props['title'] = $props['name'];
    return $props;
}

# given an array of front matter and body content, return a full post
function build_post( $front_matter, $content) {
    ksort($front_matter);
    return "---\n" . Yaml::dump($front_matter) . "---\n" . $content . "\n"; 
}

function write_file($file, $content, $overwrite = false) {
    # make sure the directory exists, in the event that the filename includes
    # a new sub-directory
    if ( ! file_exists(dirname($file))) {
        if ( FALSE === mkdir(dirname($file), 0777, true) ) {
            quit(400, 'cannot_mkdir', 'The content directory could not be created.');
        }
    }
    if (file_exists($file) && ($overwrite == false) ) {
        quit(400, 'file_conflict', 'The specified file exists');
    } 
    if ( ! $fh = fopen( $file, 'w' ) ) {
        quit(400, 'file_error', 'Unable to open Markdown file');
    }
    if ( fwrite($fh, $content ) === FALSE ) {
        quit(400, 'write_error', 'Unable to write to Markdown file');
    }
    fclose($fh);
}

function delete($request) {
    global $config;

    $filename = str_replace($config['base_url'], $config['base_path'], $request->url);
    if (false === unlink($filename)) {
        quit(400, 'unlink_failed', 'Unable to delete the source file.');
    }
    # to delete a post, simply set the "published" property to "false"
    # and unlink the relevant .html file
    $json = json_encode( array('url' => $request->url,
        'action' => 'update',
        'replace' => [ 'published' => [ false ] ]) );
    $new_request = \p3k\Micropub\Request::create($json);
    update($new_request);
}

function undelete($request) {
    # to undelete a post, simply set the "published" property to "true"
    $json = json_encode( array('url' => $request->url,
        'action' => 'update',
        'replace' => [ 'published' => [ true ] ]) );
    $new_request = \p3k\Micropub\Request::create($json);
    update($new_request);
}

function update($request) {
    $filename = get_source_from_url($request->url);
    $original = parse_file($filename);
    foreach($request->update['replace'] as $key=>$value) {
        $original[$key] = $value;
    }
    foreach($request->update['add'] as $key=>$value) {
        if (!array_key_exists($key, $original)) {
            # adding a value to a new key.
            $original[$key] = $value;
        } else {
            # adding a value to an existing key
            $original[$key] = array_merge($original[$key], $value);
        }
    }
    foreach($request->update['delete'] as $key=>$value) {
        if (!is_array($value)) {
            # deleting a whole property
            if (isset($original[$value])) {
                unset($original[$value]);
            }
        } else {
            # deleting one or more elements from a property
            $original[$key] = array_diff($original[$key], $value);
        }
    }
    #print_r($original);
    $content = $original['content'][0];
    unset($original['content']);
    $original = normalize_properties($original);
    write_file($filename, build_post($original, $content), true);
    build_site();
}

function create($request) {
    global $config;

    $mf2 = $request->toMf2();
    # grab the type of this content, less the "h-" prefix
    $type = substr($mf2['type'][0], 2);
    # make a more normal PHP array from the MF2 JSON array
    $properties = normalize_properties($mf2['properties']);

    # pull out just the content, so that $properties can be front matter
    # NOTE: content may be in ['content'] or ['content']['html'].
    if (is_array($properties['content']) && isset($properties['content']['html'])) {
        $content = $properties['content']['html'];
    } else {
        $content = $properties['content'];
    }
    # ensure that the properties array doesn't contain 'content'
    unset($properties['content']);

    # all items need a date
    if (!isset($properties['date'])) {
        $properties['date'] = date('Y-m-d H:m:s');
    }
    # explicitly mark this item as published
    $properties['published'] = true;

    if ($type == 'entry') {
        # we need either a title, or a slug.
        # NOTE: MF2 defines "name" as the title value.
        if (!isset($properties['name']) || !isset($properties['slug'])) {
            # entries need a tile.
            $properties['name'] = date('Y-m-d-His');
            #quit(400, 'insufficient_data', 'Entries require a title or a slug. Neither was provided');
        }
    }
    # if we have a title but not a slug, generate a slug
    if (isset($properties['name']) && !isset($properties['slug'])) {
        $properties['slug'] = slugify($properties['name']);
    }
    # if we have a slug but not a title, use slug as title
    if (isset($properties['slug']) && !isset($properties['name'])) {
        # make sure the slugs are safe.
        $properties['name'] = slugify($properties['slug']);
    }

    # build the entire source file, with front matter and content
    $file_contents = build_post($properties, $content);

    # produce a file name for this post.
    $path = $config['source_path'] . 'content/';
    $url = $config['base_url'];
    # does this type of content require a specific path?
    if (array_key_exists($type, $config['content_paths'])) {
        $path .= $config['content_paths'][$type];
        $url .= $config['content_paths'][$type];
    }
    $filename = $path . $properties['slug'] . '.md';
    $url .= $properties['slug'] . '/index.html';

    # write_file will default to NOT overwriting existing files,
    # so we don't need to check that here.
    write_file($filename, $file_contents);

    # build the site.
    build_site();

    # send a 201 response, so we have time to allow Hugo to generate the site
    quit(201, null, null, $url);
}

?>
