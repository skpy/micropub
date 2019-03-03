<?php
use Abraham\TwitterOAuth\TwitterOAuth;

function twitter_init($ck, $cs, $at, $atk) {
    return new TwitterOAuth($ck, $cs, $at, $atk);
}

# get a tweet ID from a tweet URL
function get_tweet_id($url = '') {
    return trim(substr($url, strrpos($url, '/')), '/');
}

# given a JSON tweet object, this will build the URL to that tweet
function build_tweet_url($tweet) {
  return 'https://twitter.com/' . $tweet->user->screen_name . '/status/' . $tweet->id_str;
}

# Tweets are fully quotable in most contexts, so these are
# all just wrappers around a single function that handles these cases.
function in_reply_to_twitter_com($properties, $content) {
    return twitter_source('in-reply-to', $properties, $content);
}
function repost_of_twitter_com($properties, $content) {
    return twitter_source('repost-of', $properties, $content);
}
function bookmark_of_twitter_com($properties, $content) {
    return twitter_source('bookmark-of', $properties, $content);
}
function in_reply_to_m_twitter_com($properties, $content) {
    return twitter_source('in-reply-to', $properties, $content);
}
function in_reply_to_mobile_twitter_com($properties, $content) {
    return twitter_source('in-reply-to', $properties, $content);
}
function repost_of_m_twitter_com($properties, $content) {
    return twitter_source('repost-of', $properties, $content);
}
function repost_of_mobile_twitter_com($properties, $content) {
    return twitter_source('repost-of', $properties, $content);
}
function bookmark_of_m_twitter_com($properties, $content) {
    return twitter_source('bookmark-of', $properties, $content);
}
function bookmark_of_mobile_twitter_com($properties, $content) {
    return twitter_source('bookmark-of', $properties, $content);
}

# replies and reposts have very similar markup, so this builds it.
function twitter_source( $type, $properties, $content) {
    global $config;
    if (!isset($config['syndication']['twitter'])) {
        return [$properties, $content];
    }

    $tweet = get_tweet($config['syndication']['twitter'], $properties[$type]);
    if ( false !== $tweet ) {
        $properties["$type-name"] = $tweet->user->name;
        $properties["$type-content"] = parse_tweet($tweet);
    } else {
        $properties["$type-name"] = "a Twitter user";
    }
    return [$properties, $content];
}

function syndicate_twitter($config, $properties, $content, $url) {
    # build our Twitter object
    $t = twitter_init($config['key'], $config['secret'], $config['token'], $config['token_secret']);

    # a pure retweet has no original content; just the source tweet.
    if (isset($properties['repost-of']) && empty($content)) {
        # we can only retweet things that originated at Twitter, so
        # confirm that the URL we're reposting is a Twitter URL.
        $host = parse_url($properties['repost-of'], PHP_URL_HOST);
        if (! in_array($host, ['mobile.twitter.com','twitter.com','www.twitter.com','twtr.io'])) {
            return false;
        }
        $id = get_tweet_id($properties['repost-of']);
        $tweet = $t->post("statuses/retweet/$id");
        if ($t->getLastHttpCode() != 200) {
            return false;
        }
        return build_tweet_url($tweet);
    }

    # Not a pure retweet.  May be a retweet with comment. May be a reply.
    # May have media.  Build up what's needed.
    $params = [] ;

    if (isset($properties['in-reply-to'])) {
        $host = parse_url($properties['in-reply-to'], PHP_URL_HOST);
        if (! in_array($host, ['mobile.twitter.com','twitter.com','www.twitter.com','twtr.io'])) {
            # we can't currently syndicate replies to non-Twitter sources.
            return false;
        }
        # replies need an ID to which they are replying.
        $params['in_reply_to_status_id'] = get_tweet_id($properties['in-reply-to']);
        $params['auto_populate_reply_metadata'] = true;
    }

    if (isset($properties['photo']) && !empty($properties['photo'])) {
        # if this post has photos, upload them to Twitter, and obtain
        # the relevant media ID, for inclusion with the tweet.
        $photos = [];
        foreach($properties['photo'] as $p) {
            $upload = $t->upload('media/upload', ['media' => $p]);
            if ($t->getLastHttpCode() == 200) {
                $photos[] = $upload->media_id_string;
            }
        }
        if (!empty($photos)) {
            $params['media_ids'] = implode(',', $photos);
        }
    }

    if (isset($properties['title'])) {
        # we're announcing a new article. The user should have some prefix
        # defined in the config to tweet in front of the title of the post,
        # followed by the URL of the post.
        $params['status'] = $config['prefix'] . $properties['title'] . "\n" . $url;
    } else {
        # no title means this is a "note".  So just post the content directly.
        $params['status'] = $content;
        # if this is a retweet with comment, append the URL of the tweet
        # https://twittercommunity.com/t/method-to-retweet-with-comment/35330/21
        if (isset($properties['repost-of'])) {
            $params['status'] .= ' ' . $properties['repost-of'];
        }
    }
    $tweet = $t->post('statuses/update', $params);
    if (! $t->getLastHttpCode() == 200) {
        return false;
    }
    return build_tweet_url($tweet); // in case we want to do something with this.
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

# this takes a tweet and replaces all the t.co links with real ones,
# as well as link to user names and display media.
# it will recursively display one quoted tweet in the same way.
function parse_tweet ($tweet, $recurse = 0) {
    $text = $tweet->full_text;

    if (! empty($tweet->entities->urls)) {
      foreach ($tweet->entities->urls as $url) {
        $text = preg_replace('#' . preg_quote($url->url) . '#', $url->expanded_url, $text);
      }
    }

    if (! empty($tweet->entities->user_mentions)) {
      foreach ($tweet->entities->user_mentions as $user) {
        $text = preg_replace('#@' . preg_quote($user->screen_name) . '#', '<a href="https://twitter.com/' . $user->screen_name . '">@' . $user->screen_name . '</a>', $text);
      }
    }

    if (! empty($tweet->entities->media)) {
      foreach ($tweet->entities->media as $media) {
        if ($media->type == 'photo') {
          $text = preg_replace('#' . preg_quote($media->url) . '#', '<img src="' . $media->media_url_https . '" />', $text);
        }
      }
    }

    if ($tweet->is_quote_status == 1 && $recurse == 0) {
      $quote = parse_tweet($tweet->quoted_status, 1);
      $quote = '<blockquote><p>' . parse_tweet($tweet->quoted_status, $recurse) . '</p><cite><a href="https://twitter.com/' . $tweet->quoted_status->user->screen_name . '/status/' . $tweet->quoted_status->id_str . '">' . $tweet->quoted_status->user->name . '</a></cite></blockquote>';
      $quote_url = 'https://twitter.com/' . $tweet->quoted_status->user->screen_name . '/status/' . $tweet->quoted_status->id_str;
      $text = str_replace($quote_url, '', $text);;
      $text = $quote . $text;
    }
    return $text;
}
