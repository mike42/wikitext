#!/bin/sh
set -eu

# Get interwiki table from mediawiki.org. This is a sane default for most sites.
url="https://www.mediawiki.org/w/api.php?action=query&meta=siteinfo&siprop=interwikimap&format=json"
dest="../src/Mike42/Wikitext/interwiki.json"
curl "$url" | json_reformat > "$dest"
