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

    public function example(): array
    {
        return [
            ["<p>Testing 1 2 3</p>\n", "Testing 1 2 3"],
            ["<p>Link : <a href=\"yolo\" title=\"yolo\">yolo</a></p>\n", "Link : [[yolo]]"],
            ["<p>Link : <a href=\"https://github.com\" class=\"external\">Github</a></p>\n", "Link : [https://github.com Github]"],
            ["<table>\n<tr>\n<td>aaa</td>\n</tr>\n</table>\n", "{|\n|aaa\n|}"],
            ["<p><a href=\"hungary\"><img src=\"Flag of Hungary vertical.jpg\" alt=\"yolo\" /></a></p>\n", "[[File:Flag of Hungary vertical.jpg|bottom|8px|link=hungary|alt=yolo]]"]
        ];
    }

    /** @dataProvider example */
    public function testExample($expected, $wikitext)
    {
        $this->assertParsingEquals($expected, $wikitext);
    }

}
