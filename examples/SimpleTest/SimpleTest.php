#!/usr/bin/php
<?
require_once("../../wikitext.php");
$input = file_get_contents("input.txt");

/* The most rudimentary way to invoke the parser */
WikitextParser::init();
$parser = new WikitextParser($input);
$output = $parser -> result;

file_put_contents("output.html", $output);
?>
