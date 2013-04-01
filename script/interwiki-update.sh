#!/bin/sh
# Get interwiki table from mediawiki.org. This is a sane default for most sites.
url='http://www.mediawiki.org/w/api.php?action=query&meta=siteinfo&siprop=interwikimap&format=php'
dest='../interwiki-default.ser'
wget -O $dest $url
