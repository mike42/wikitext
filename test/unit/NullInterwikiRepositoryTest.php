<?php

/*
 * Wikitext
 */

namespace Test\Mike42\Wikitext;

class NullInterwikiRepositoryTest extends \PHPUnit\Framework\TestCase
{

    protected $sut;

    protected function setUp(): void
    {
        $this->sut = new \Mike42\Wikitext\NullInterwikiRepository();
    }

    public function testEmpty()
    {
        $this->assertFalse($this->sut->hasNamespace('yolo'));
    }

    public function testSecurlyImplementingNullObject()
    {
        $this->expectException(\LogicException::class);
        $this->sut->getTargetUrl('yoolo');
    }

}
