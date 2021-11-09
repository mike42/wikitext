<?php

namespace Mike42\Wikitext;

class WikitextParserTest extends \PHPUnit\Framework\TestCase
{

    protected $sut;

    protected function setUp(): void
    {
        $this->sut = new WikitextParser(new DefaultParserBackend());
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

    public function testTable()
    {
        $this->assertParsingEquals("<table>\n<tr>\n<td>aaa</td>\n</tr>\n</table>\n", "{|\n|aaa\n|}");
    }

    public function testImage()
    {
        $this->assertParsingEquals("<p><a href=\"hungary\"><img src=\"Flag of Hungary vertical.jpg\" alt=\"yolo\" /></a></p>\n", "[[File:Flag of Hungary vertical.jpg|bottom|8px|link=hungary|alt=yolo]]");
    }

}
