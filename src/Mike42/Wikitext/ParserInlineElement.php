<?php

namespace Mike42\Wikitext;

/**
 * Stores inline elements
 */
class ParserInlineElement
{
    public $startTag;
    public $endTag;
    public $argSep;
    public $argNameSep;
    public $hasArgs;
    
    public function __construct($startTag, $endTag, $argSep = '', $argNameSep = '', $argLimit = 0)
    {
        $this -> startTag = $startTag;
        $this -> endTag = $endTag;
        $this -> argSep = $argSep;
        $this -> argNameSep = $argNameSep;
        $this -> argLimit = $argLimit;
        $this -> hasArgs = $this -> argSep != '';
    }
}
