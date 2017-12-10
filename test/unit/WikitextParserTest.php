<?php
use Mike42\Wikitext\WikitextParser;

class WikitextParserTest extends \PHPUnit\Framework\TestCase
{
    public function testParse()
    {
        $expected = "<p>Testing 1 2 3</p>\n";
        $actual = WikitextParser::parse("Testing 1 2 3");
        $this -> assertEquals($expected, $actual);
    }
}

?>
