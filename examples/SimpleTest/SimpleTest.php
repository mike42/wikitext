#!/usr/bin/php
<?php
require_once(__DIR__ . "/../../vendor/autoload.php");
use Mike42\Wikitext\WikitextParser;

$input = file_get_contents("input.txt");

/* The most rudimentary way to invoke the parser */
$parser = new WikitextParser($input);
$output = $parser -> result;

file_put_contents("output.html", $output);
?>
