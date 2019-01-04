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
        # we want the "photo" property to be an array, even if it's a
        # single element.  Our Hugo templates require this.
        if ($k == 'photo') {
            $props[$k] = $v;
        } elseif (is_array($v) && count($v) === 1) {
            $props[$k] = $v[0];
        } else {
            $props[$k] = $v;
        }
    }
    # MF2 defines "name" instead of title, but Hugo wants "title".
    # Only assign a title if the post has a name.
    if (isset($props['name'])) {
        $props['title'] = $props['name'];
    }
    return $props;
}

# this function is a router to other functions that can operate on the source
# URLs of reposts, replies, bookmarks, etc.
# $type = the indieweb type (https://indieweb.org/post-type-discovery)
# $properties = array of front-matter properties for this post
# $content = the content of this post (which may be an empty string)
#
function posttype_source_function($posttype, $properties, $content) {
    # replace all hyphens with underscores, for later use
    $type = str_replace('-', '_', $posttype);
    # get the domain of the site to which we are replying, and convert
    # all dots to underscores.
    $target = str_replace('.', '_', parse_url($properties[$posttype], PHP_URL_HOST));
    # if a function exists for this type + target combo, call it
    if (function_exists("${type}_${target}")) {
        list($properties, $content) = call_user_func("${type}_${target}", $properties, $content);
    }
    return [$properties, $content];
}

# this function accepts the properties of a post and
# tries to perform post type discovery according to
# https://indieweb.org/post-type-discovery
# returns the MF2 post type
function post_type_discovery($properties) {
    $vocab = array('rsvp',
                 'in-reply-to',
                 'repost-of',
                 'like-of',
                 'bookmark-of',
                 'photo');
    foreach ($vocab as $type) {
        if (isset($properties[$type])) {
            return $type;
        }
    }
    # articles have titles, which Micropub defines as "name"
    if (isset($properties['name'])) {
        return 'article';
    }
    # no other match?  Must be a note.
    return 'note';
}

# given an array of front matter and body content, return a full post
# Articles are full Markdown files; everything else is just YAML blobs
# to be appended to a data file.
function build_post( $front_matter, $content) {
    ksort($front_matter);
    if ($front_matter['posttype'] == 'article') {
      return "---\n" . Yaml::dump($front_matter) . "---\n" . $content . "\n";
    } else {
      $front_matter['content'] = $content;
      return Yaml::dump(array($front_matter), 2, 2);
    }
}

function write_file($file, $content, $overwrite = false) {
    # make sure the directory exists, in the event that the filename includes
    # a new sub-directory
    if ( ! file_exists(dirname($file))) {
        check_target_dir(dirname($file));
    }
    if (file_exists($file) && ($overwrite == false) ) {
        quit(400, 'file_conflict', 'The specified file exists');
    }
    if ( FALSE === file_put_contents( $file, $content ) ) {
        quit(400, 'file_error', 'Unable to open Markdown file');
    }
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
    $content = $original['content'][0];
    unset($original['content']);
    $original = normalize_properties($original);
    write_file($filename, build_post($original, $content), true);
    build_site();
}

function create($request, $photos = []) {
    global $config;

    $mf2 = $request->toMf2();
    # make a more normal PHP array from the MF2 JSON array
    $properties = normalize_properties($mf2['properties']);

    # pull out just the content, so that $properties can be front matter
    # NOTE: content may be in ['content'] or ['content']['html'].
    # NOTE 2: there may be NO content!
    if (isset($properties['content'])) {
        if (is_array($properties['content']) && isset($properties['content']['html'])) {
            $content = $properties['content']['html'];
        } else {
            $content = $properties['content'];
        }
    } else {
        $content = '';
    }
    # ensure that the properties array doesn't contain 'content'
    unset($properties['content']);

    if (!empty($photos)) {
        # add uploaded photos to the front matter.
        if (!isset($properties['photo'])) {
            $properties['photo'] = $photos;
        } else {
            $properties['photo'] = array_merge($properties['photo'], $photos);
        }
    }
    if (!empty($properties['photo'])) {
        $properties['thumbnail'] = preg_replace('#-' . $config['max_image_width'] . '\.#', '-200.', $properties['photo']);
    }

    # figure out what kind of post this is.
    $properties['posttype'] = post_type_discovery($properties);

    # invoke any source-specific functions for this post type.
    # articles, notes, and photos don't really have "sources", other than
    # their own content.
    # replies, reposts, likes, bookmarks, etc, should reference source URLs
    # and may interact with those sources here.
    if (! in_array($properties['posttype'], ['article', 'note', 'photo'])) {
        list($properties, $content) = posttype_source_function($properties['posttype'], $properties, $content);
    }

    # all items need a date
    if (!isset($properties['date'])) {
        $properties['date'] = date('Y-m-d H:i:s');
    }

    if (isset($properties['post-status'])) {
        if ($properties['post-status'] == 'draft') {
            $properties['published'] = false;
        } else {
            $properties['published'] = true;
        }
        unset($properties['post-status']);
    } else {
        # explicitly mark this item as published
        $properties['published'] = true;
    }

    # we need either a title, or a slug.
    # NOTE: MF2 defines "name" as the title value.
    if (!isset($properties['name']) && !isset($properties['slug'])) {
        # We will assign this a slug.
        # Hex value of seconds since UNIX epoch
        $properties['slug'] = dechex(date('U'));
    }

    # if we have a title but not a slug, generate a slug
    if (isset($properties['name']) && !isset($properties['slug'])) {
        $properties['slug'] = $properties['name'];
    }
    # make sure the slugs are safe.
    if (isset($properties['slug'])) {
        $properties['slug'] = slugify($properties['slug']);
    }

    # build the entire source file, with front matter and content for articles
    # or YAML blobs for notes, etc
    $file_contents = build_post($properties, $content);

    if ($properties['posttype'] == 'article') {
        # produce a file name for this post.
        $path = $config['source_path'] . 'content/';
        $url = $config['base_url'] . $properties['slug'] . '/index.html';
        $filename = $path . $properties['slug'] . '.md';
        # write_file will default to NOT overwriting existing files,
        # so we don't need to check that here.
        write_file($filename, $file_contents);
    } else {
        # this content will be appended to a data file.
        # our config file defines the content_path of the desired file.
        $content_path = $config['content_paths'][$properties['posttype']];
        $yaml_path = $config['source_path'] . 'data/' . $content_path . '.yaml';
        $md_path = $config['source_path'] . 'content/' . $content_path . '.md';
        $url = $config['base_url'] . $content_path . '/#' . $properties['slug'];
        check_target_dir(dirname($yaml_path));
        check_target_dir(dirname($md_path));
        if (! file_exists($yaml_path)) {
            # prep the YAML for our note which will follow
            file_put_contents($yaml_path, "---\nentries:\n");
        }
        file_put_contents($yaml_path, $file_contents, FILE_APPEND);
        # now we need to create a Markdown file, so that Hugo will
        # build the file for public consumption.
        # NOTE: we may want to override the post type here, so that we
        #       can use a singular Hugo theme for multiple post types.
        if (array_key_exists($properties['posttype'], $config['content_overrides'])) {
            $content_type = $config['content_overrides'][$properties['posttype']];
        } else {
            $content_type = $properties['posttype'];
        }
        if (! file_exists($md_path)) {
            file_put_contents($md_path, "---\ntype: $content_type\n---\n");
        }
        # we may need to create a _index.md file so that a section template
        # can be generated. If the content_path has any slashes in it, that
        # means that sub-directories are defined, and thus a section index
        # is required.
        if (FALSE !== strpos($content_path, '/')) {
            $section_path = dirname($config['source_path'] . 'content/' . $content_path) . '/_index.md';
            file_put_contents($section_path, "---\ntype: $content_type\n---\n");
        }
    }

    # build the site.
    build_site();

    # allow the client to move on, while we syndicate this post
    header('HTTP/1.1 201 Created');
    header('Location: ' . $url);

    # syndicate this post
    $syndication_targets = array();
    # some post kinds may enforce syndication, even if the Micropub client
    # did not send an mp-syndicate-to parameter. This code finds those post
    # kinds and sets the mp-syndicate-to.
    if (isset($config['always_syndicate'])) {
        if (array_key_exists($properties['posttype'], $config['always_syndicate'])) {
            foreach ($config['always_syndicate'][$properties['posttype']] as $target) {
                $syndication_targets[] = $target;
            }
        }
    }
    if (isset($request->commands['mp-syndicate-to'])) {
        $syndication_targets = array_unique(array_merge($syndication_targets, $request->commands['mp-syndicate-to']));
    }
    if (! empty($syndication_targets)) {
        # ensure we don't have duplicate syndication targets
        foreach ($syndication_targets as $target) {
            if (function_exists("syndicate_$target")) {
                $syndicated_url = call_user_func("syndicate_$target", $config['syndication'][$target], $properties, $content, $url);
                if (false !== $syndicated_url) {
                    $syndicated_urls["$target-url"] = $syndicated_url;
                }
            }
        }
        if (!empty($syndicated_urls)) {
            # convert the array of syndicated URLs into scalar key/value pairs
            # if this is an article let's just re-write it,
            # with the new properties in the front matter.
            # NOTE: we are NOT rebuilding the site at this time.
            #       I am unsure whether I even want to display these
            #       links.  But it's easy enough to collect them, for now.
            if ($properties['posttype'] == 'article') {
                foreach ($syndicated_urls as $k => $v) {
                    $properties[$k] = $v;
                }
                $file_contents = build_post($properties, $content);
                write_file($filename, $file_contents, true);
            } else {
                # this is not an article, so we should be able to simply
                # append the syndicated URL to the YAML data file
                foreach ($syndicated_urls as $k => $v) {
                  file_put_contents($yaml_path, "  $k: $v\n", FILE_APPEND);
                }
            }
        }
    }
    # send a 201 response, with the URL of this item.
    quit(201, null, null, $url);
}

?>
