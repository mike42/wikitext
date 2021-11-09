<?php

namespace Mike42\Wikitext;

class WikitextParserTest extends \PHPUnit\Framework\TestCase
{

    protected $sut;

    protected function setUp()
    {
        $this->sut = new WikitextParser();
    }

    protected function assertParsingEquals(string $expected, string $wikitext): void
    {
        $this->assertEquals($expected, $this->sut->parse($wikitext));
    }

    public function testParse()
    {
        $this->assertParsingEquals("<p>Testing 1 2 3</p>\n", "Testing 1 2 3");
    }

    public function testLink()
    {
        $this->assertParsingEquals("<p>Link : <a href=\"yolo\" title=\"yolo\">yolo</a></p>\n", "Link : [[yolo]]");
    }

}
