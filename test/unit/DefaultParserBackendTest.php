<?php
use Mike42\Wikitext\DefaultParserBackend;

class DefaultParserBackendTest extends \PHPUnit\Framework\TestCase
{
    public function testGetTemplateMarkup()
    {
        $backend = new DefaultParserBackend();
        $expected = "[[test]]";
        $actual = $backend -> getTemplateMarkup("test");
        $this -> assertEquals($expected, $actual);
    }
}

?>
