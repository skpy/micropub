# micropub
a minimal PHP micropub endpoint for static sites, with media support

This is a micropub solution for static sites generated with [Hugo](https://gohugo.io/). It will accept most actions as defined in the [Micropub standard](https://www.w3.org/TR/micropub/), and upon completion will invoke Hugo and rebuild your site.

This is based heavily off of the following projects:
* [rhiaro's MVP micropub](https://rhiaro.co.uk/2015/04/minimum-viable-micropub)
* [dgold's Nanopub](https://github.com/dg01d/nanopub/)
* [aaronpk's MVP Media Endpoint](https://gist.github.com/aaronpk/4bee1753688ca9f3036d6f31377edf14)

This works **for me**, following the principles of [self dog fooding](https://indieweb.org/selfdogfood).  Rather then develop a universal widget that might work for all possible implementations, I built what I needed.  Hopefully this serves as an inspiration for others, in the same way that those projects linked above heavily inspired me.

## Installation
If you're using Hugo, you can simply clone this repo into the `/public` directory of your active website.  Files that exist in `/public` but which do not exist in your `/content` or `/static` directories will not be overwritten.

Alternately, you could clone this to the `/static` directory, and have Hugo (re)copy is into place with every site build.

```
git clone https://github.com/skpy/micropub.git
cd micropub
php composer.phar install
cp config.php.sample config.php
vi config.php
```
Edit the config values as needed for your site.

Add the necessary markup to your HTML templates to declare your Micropub endpoint:
```
<link rel="micropub" href="https://skippy.net/micropub/index.php">
```

Now point a Micropub client at your site, and start creating content!

### Syndication
Content you create can be syndicated to external services. Right now, only Twitter is supported; but adding additional syndication targets should be straightforward.

Each syndication target is required to have configuration declared in the `syndication` array in `config.php`.  Then, each syndication target should have a function `syndication_<target>`, where <target> matches the name of the array key in `config.php`.  Each such function is expected to return the URL of the syndicated copy of this post, which will be added to the front matter of the post.

### Source URLs
Replies, reposts, bookmarks, etc all define a source URL. This server can interact with those sources on a per-target basis.  Right now, the only supported source is Twitter.  If the source of a reply, repost, or bookmark is a Tweet, the original tweet will be retreived, and stored in the front matter of the post.  Your theme may then elect to use this as needed.  In this way, we can preserve historical context of your activities, and allow you to display referenced data as you need.

Additional sources can be added, much like syndication.  To define a new source, create a new function that matches the format `<post_type>_<source_domain_name>`.  Convert all dots in the domain name to underscores.  For example, the Twitter source functions use `in_reply_to_twitter_com` and `repost_of_twitter_com` and `bookmark_of_twitter_com`.

The Twitter source also defines `<post_type>_m_twitter_com`, which are simple wrappers to ensure that this functionality works when using mobile-friendly URLs.

See `inc/twitter.php` for the implementation details.

## How I use this
I have my Hugo site in `/var/www/skippy.net`.  I have my [web server](https://caddyserver.com/) configured to use `/var/www/skippy.net/public` as the document root of my site.  All of `/var/www/skippy.net/content` and `/var/www/skippy.net/static` are owned by the `www-data` user, to ensure that content can be created, edited, and deleted through Micropub without permission problems.

When I create a new post, Micropub will generate the file in `/var/www/skippy.net/content/`, and then invoke Hugo, which will recreate all the content in `/var/www/skippy.net/public`.  No extra steps are required, and the new content is available.

I am making heavy use of [Hugo data files](https://gohugo.io/templates/data-templates/) with this Micropub server. Notes, photos, replies, reposts, bookmarks, and likes are all stored as YAML arrays in files in the `/data` directory. This allows new content to be appended quickly, and reduces the number of content files that Hugo needs to parse when building the site.

At this time, editing and deleting any content stored in a data file is **not supported**. Editing and deleting is only supported for articles.

The media endpoint automatically generates thumbnails with a maximum width of 200 pixels. The array of links to these is stored in the `$properties['thumbnail']` property.
