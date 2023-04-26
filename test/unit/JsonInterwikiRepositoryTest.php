<?php

/*
 * Wikitext
 */

class JsonInterwikiRepositoryTest extends \PHPUnit\Framework\TestCase
{

    protected $sut;

    protected function setUp(): void
    {
        $this->sut = new \Mike42\Wikitext\JsonInterwikiRepository(__DIR__ . '/sample_interwiki.json');
    }

    public function testEmpty()
    {
        $this->assertFalse($this->sut->hasNamespace('yolo'));
        $this->assertTrue($this->sut->hasNamespace('en'));
    }

    public function testGetTargetUrl()
    {
        $this->assertEquals('https://en.wikipedia.org/wiki/$1', $this->sut->getTargetUrl('en'));
    }

}
