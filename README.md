# micropub
a minimal PHP micropub endpoint for static sites, with media support

This is a micropub solution for static sites generated with [Hugo](https://gohugo.io/). It will accept most actions as defined in the [Micropub standard](https://www.w3.org/TR/micropub/), and upon completion will invoke Hugo and rebuild your site.

This is based heavily off of the following projects:
* [rhiaro's MVP micropub](https://rhiaro.co.uk/2015/04/minimum-viable-micropub)
* [dgold's Nanopub](https://github.com/dg01d/nanopub/)
* [aaronpk's MVP Media Endpoint](https://gist.github.com/aaronpk/4bee1753688ca9f3036d6f31377edf14)

This works **for me**, following the principles of [self dog fooding](https://indieweb.org/selfdogfood).  Rather then develop a universal widget that might work for all possible implementation, I built what I needed.  Hopefully this serves as an inspiration for others, in the same way that those projects linked above heavily inspired me.
