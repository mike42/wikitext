<?php

namespace Mike42\Wikitext;

class ParserTableElement
{
    public $lineStart;  /* Token appearing at start of line */
    public $argsep;
    public $limit;
    public $inlinesep;

    public function __construct($lineStart, $argsep, $inlinesep, $limit)
    {
        $this -> lineStart = $lineStart;
        $this -> argsep = $argsep;
        $this -> inlinesep = $inlinesep;
        $this -> limit = $limit;
    }
}
