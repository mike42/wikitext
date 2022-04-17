# Wikitext parser [![Build Status](https://travis-ci.org/mike42/wikitext.svg?branch=master)](https://travis-ci.org/mike42/wikitext)

This library can be used to add basic wikitext (Mediawiki-style) support to a PHP app.
Its role is, by no means, to replace the Parsoid library from Wikimedia foundation, but to use the
same syntaxic core of wikitext. Furthermore, you can extend this parser very easily to your project 
specifications (specially for url generation).

This repository was forked from the [abandoned project mike42.me/wikitext](http://mike42.me/wikitext/), 
that's why I've created a new package on Packagist.

Code may be re-mixed and re-used under the MIT licence. See 'examples' folder for usage.

# Notes about this fork
* Abstracting
* Removing anti-patterns
* unit testing
* Template method design pattern for extending the html rendering
