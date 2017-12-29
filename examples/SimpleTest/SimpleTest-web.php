<?php
# Version of SimpleTest to open in a web-browser
require_once(__DIR__ . "/../../vendor/autoload.php");
use Mike42\Wikitext\WikitextParser;

$input = file_get_contents(__DIR__ . "/input.txt");

$parser = new WikitextParser($input);
echo $parser -> result;
