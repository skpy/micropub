# micropub
a minimal PHP micropub endpoint, with media support

This is based heavily off of the following projects:
* [rhiaro's MVP micropub](https://rhiaro.co.uk/2015/04/minimum-viable-micropub)
* [dgold's Nanopub](https://github.com/dg01d/nanopub/)
* [aaronpk's MVP Media Endpoint](https://gist.github.com/aaronpk/4bee1753688ca9f3036d6f31377edf14)

My personal setup is a little convoluted. I run a variety of sites on my server, all with different document roots.  I run PHP in a container, which mounts my host's `/var/www/html` into the container.  On my host, `/var/www/html` holds a WordPress multi-site setup.  My other sites are all static sites generated with [Hugo](https://gohugo.io/).

I use [Caddy](https://caddyserver.com/) as my web server. In my static sites, I have the following directive to make all PHP requests work with the PHP container:
```
  fastcgi / 127.0.0.1:9000 php { root /var/www/html }
```
But because PHP is running in a container, it does not have access to anything outside of `/var/www/html`.  In order to get my micropub-published files into my static sites, I use [incron](http://inotify.aiken.cz/?section=incron&page=about&lang=en). I create a new `incrontab` entry for each static site that should be micropub-enabled, to watch a directory that corresponds with the site's domain name.  When a new file is written, the `micropub.sh` script in this repository will execute, copying and moving the files as necessary.  If it's a Markdown file, `hugo` is invoked to rebuild the site.

The `micropub.sh` script copies AND moves images. This is so that micropub endpoints can see and access uploaded images without requiring a full site rebuild. The image is copied into the `/images/` directory of the site's docroot, and moved to the `/static/images` directory of the source of my Hugo site.

The `is.php` script in this repo is an example of how to use most of this functionality **without** a full micropub setup. I use it to power [https://skippy.is/](https://skippy.is/) for easy uploading from my phone. It builds the Markdown file in exactly the way I want with minimal input from me.
