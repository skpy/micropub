<?php
use Abraham\TwitterOAuth\TwitterOAuth;

function twitter_init($ck, $cs, $at, $atk) {
    return new TwitterOAuth($ck, $cs, $at, $atk);
}

function get_tweet_id($url = '') {
    return trim(substr($url, strrpos($url, '/')), '/');
}

# Tweets are fully quotable in reply or repost context, so these are
# all just wrappers around a single function that handles both cases.
function twitter_com_in_reply_to($properties, $content) {
    return twitter_reply_or_repost('in-reply-to', $properties, $content);
}
function twitter_com_repost_of($properties, $content) {
    return twitter_reply_or_repost('repost-of', $properties, $content);
}
function m_twitter_com_in_reply_to($properties, $content) {
    return twitter_reply_or_repost('in-reply-to', $properties, $content);
}
function m_twitter_com_repost_of($properties, $content) {
    return twitter_reply_or_repost('repost-of', $properties, $content);
}

# replies and reposts have very similar markup, so this builds it.
function twitter_reply_or_repost( $type, $properties, $content) {
    global $config;
    if (!isset($config['syndication']['twitter'])) {
        return [$properties, $content];
    }
    $properties['posttype'] = $type;

    # we'll use this when building a reply.
    $suffix = '<div class="e-content">' . $content . '</div>';

    $tweet = get_tweet($config['syndication']['twitter'], $properties[$type]);
    if ( false !== $tweet ) {
        $properties["$type-name"] = $tweet->user->name;
        if ($type == 'in-reply-to') {
            $prefix = '<div class="u-in-reply-to h-cite">In reply to ';
        }
        if ($type == 'repost-of') {
            $prefix = '<div class="u-repost-of h-cite">Repost of ';
            $suffix = '';
        }
        $markup = '<a class="u-url p-author" href="' . $properties[$type] . '">' . $tweet->user->name . '</a>: <blockquote class="p-content">' . $tweet->full_text . '</blockquote></div>';
    } else {
        # problem getting the tweet; so make a best effort to display something
        # useful.
        if ($type == 'in-reply-to') {
            $prefix = '<div class="u-in-reply-to">In reply to ';
        }
        if ($type == 'repost-of') {
            $prefix = '<div class="u-repost-of">Repost of ';
            $suffix = '';
        }
        $markup = '<a class="u-url" href="' . $properties[$type] . '">' . $properties[$type] . '</a>: </div>';
    }
    $content = $prefix . $markup . $suffix;
    return [$properties, $content];
}

function syndicate_twitter($config, $properties, $content, $url) {
    # build our Twitter object
    $t = twitter_init($config['key'], $config['secret'], $config['token'], $config['token_secret']);

    if (isset($properties['repost-of'])) {
        $id = get_tweet_id($properties['repost-of']);
        $t->post('statuses/retweet/', ['id' => $id]);
        # perform the retweet and return; no need to do more.
        # NOTE: we don't check the return code here because this is the
        # of processing.  I don't feel like retrying a retweet.
        return;
    }

    # not a retweet.  May be a reply.  May have media.  Build up what's needed.
    $params = [] ;

    if (isset($properties['in-reply-to'])) {
        # replies need an ID to which they are replying.
        $params['in_reply_to_status_id'] = get_tweet_id($properties['in-reply-to']);
        $params['auto_populate_reply_metadata'] = true;
    }

    if (isset($properties['photo']) && !empty($properties['photo'])) {
        # if this post has photos, upload them to Twitter, and obtain
        # the relevant media ID, for inclusion with the tweet.
        $photos = [];
        foreach($properties['photo'] as $p) {
            $photos[] = $t->upload('media/upload', ['media' => $p]);
        } 
        $params['media_ids'] = implode(',', $photos);
    }

    if (isset($properties['title'])) {
        # we're announcing a new article. The user should have some prefix
        # defined in the config to tweet in front of the title of the post,
        # followed by the URL of the post.
        $params['status'] = $config['prefix'] . $properties['title'] . "\n" . $url;
    } else {
        # no title means this is a "note".  So just post the content directly.
        $params['status'] = $content;
    }
    $tweet = $t->post('statuses/update', $params);
    if (! $t->getLastHttpCode() == 200) {
        return false;
    }
    return $tweet; // in case we want to do something with this.
}

function get_tweet($config, $url) {
    $t = twitter_init($config['key'], $config['secret'], $config['token'], $config['token_secret']);
    $id = get_tweet_id($url);
    $tweet = $t->get("statuses/show", ['id' => $id, 'tweet_mode' => 'extended']);
    if (! $t->getLastHttpCode() == 200) {
        // error :(
        return false;
    }
    return $tweet;
}
