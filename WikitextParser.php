<?php
/*
  Library to add wikitext support to a web app -- http://mike.bitrevision.com/wikitext/

  Copyright (C) 2012 Michael Billington <michael.billington@gmail.com>

  Permission is hereby granted, free of charge, to any person obtaining a copy of this software and
  associated documentation files (the "Software"), to deal in the Software without restriction,
  including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense,
  and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so,
  subject to the following conditions:

  The above copyright notice and this permission notice shall be included in all copies or substantial
  portions of the Software.

  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED,
  INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A
  PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
  HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF
  CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE
  SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/
class WikitextParser {
	public static $version = "0.4";

	public static $block;
	public static $inline;
	public static $lineBlock;
	public static $backend;
	public static $tableBlock;
	public static $tableStart;
	
	public static $inlineLookup;
	
	public $markupBlob;
	public $result;
	
	/**
	 * Definitions for tokens with special meaning to the parser
	 */
	public static function init() {
		/* Non fully-nestable blocks, used to strip out different general blocks of markup which need to be parsed */
		self::$block = array(
			'nowiki'        => new ParserBlockElement('<nowiki>',      '</nowiki>'),
			'includeonly'   => new ParserBlockElement('<includeonly>', '</includeonly>'),
			'noinclude'     => new ParserBlockElement('<noinclude>',   '</noinclude>'));
		
		/* Table elements. These are parsed separately to the other elements */
		self::$tableStart =	new ParserInlineElement("{|",		"|}");
		
		self::$tableBlock = array(
			'tr'      => new ParserTableElement('|-', '', '', ''),
			'th'      => new ParserTableElement('!', '!', '!!', 1),
			'td'      => new ParserTableElement('|', '|', '||', 1),
			'caption' => new ParserTableElement('|+', '', '', 0));

		/* Inline elemens. These are parsed recursively and can be nested as deeply as the system will allow. */
		self::$inline = array(
			'nothing'    => new ParserInlineElement('', 			''),
			'template'   => new ParserInlineElement('{{', 		'}}', 	'|', '='),
			'a_internal' => new ParserInlineElement('[[', 		']]', 	'|', '='),
			'a_external' => new ParserInlineElement('[', 		']', 	' ',  '', 1),
			'bold'       => new ParserInlineElement("'''", 		"'''"),
			'italic'     => new ParserInlineElement("''", 		"''"));

		/* Create lookup table for efficiency */
		$inlineLookup = array();
		foreach(self::$inline as $key => $token) {
			if(mb_strlen($token -> startTag) != 0) {
				$c = mb_substr($token -> startTag, 0, 1);
				if(!isset($inlineLookup[$c])) {
					$inlineLookup[$c] = array();
				}
				$inlineLookup[$c][$key] = self::$inline[$key];
			}
		}
		self::$inlineLookup = $inlineLookup;
		self::$backend = new DefaultParserBackend();

		/* Line-block elements. These are characters which have a special meaning at the start of lines, and use the next end-line as a close tag. */
		self::$lineBlock = array(
			'pre' => new ParserLineBlockElement(array(" "),      array(),    1,     false),
			'ul'  => new ParserLineBlockElement(array("*"),      array(),    0,     true),
			'ol'  => new ParserLineBlockElement(array("#"),      array(),    0,     true),
			'dl'  => new ParserLineBlockElement(array(":", ";"), array(),    0,     true),
			'h'   => new ParserLineBlockElement(array("="),      array("="), 6,     false));
	}
	
	/**
	 * Parse a given document/page of text (main entry point)
	 * 
	 * @param unknown_type $text
	 * @param unknown_type $included
	 */
	public function parse($text) {
		$parser = new WikitextParser($text);
		return $parser -> result;
	}	
	
	/**
	 * Initialise a new parser object and parse a standalone document.
	 * If templates are included, each will processed by a different instance of this object
	 * 
	 * @param string $text The text to parse
	 */
	public function WikitextParser($text, $included = false) {
		/* Always strip out and store <nowiki> blocks */
		$text = $this -> stripBlock('nowiki', $text, true);
		if($included) {
			/* If included, strip out <noinclude> blocks and throw them away */
			$text = $this -> stripBlock('noinclude', $text, false);
		} else {
			/* If not included, strip out <includeonly> blocks and throw them away */
			$text = $this -> stripBlock('includeonly', $text, false);
		}
		
		/* Now divide into paragraphs */
		$sections = explode("\n\n", str_replace("\r\n", "\n", $text));
		$newtext = "";
		foreach($sections as $section) {
			/* Newlines at the start/end have special meaning (compare to how this is called from parseLineBlock) */
			$result = $this -> parseInline("\n".$section, 'p');
			$newtext .= $result['parsed'];
		}

		$this -> result = $newtext;
	}
	
	/**
	 * Parse a block of wikitext looking for inline tokens, indicating the start of an element.
	 * Calls itself recursively to search inside those elements when it finds them
	 * 
	 * @param string $text Text to parse
	 * @param $token The name of the current inline element, if inside one.
	 */
	private function parseInline($text, $token = '') {
		/* Quick escape if we've run into a table */
		$inParagraph = false;
		if($token == '' || !isset(self::$inline[$token])) {
			/* Default to empty token if none is set (these have no end token, ensuring there will be no remainder after this runs) */
			if($token == 'p') {
				/* Blocks of text here need to be encapsualted in paragraph tags */
				$inParagraph = true;
			}
			$inlineElement = self::$inline['nothing'];
		} else {
			$inlineElement = self::$inline[$token];
		}
		
		$parsed = ''; // For completely parsed text
		$buffer = ''; // For text which may still be encapsulated or chopped up
		$remainder = '';
		
		$arg = array();
		$curKey = '';
		
		$len = mb_strlen($text);
		for($i = 0; $i < $len; $i++) {
			/* Looping through each character */
			$hit = false; // State so that the last part knows whether to simply append this as an unmatched character
			
			/* Looking for this element's close-token */
			if(mb_strlen($inlineElement -> endTag) != 0 && $inlineElement -> endTag == mb_substr($text, $i, mb_strlen($inlineElement -> endTag))) {
				/* Hit a close tag: Stop parsing here, return the remainder, and let the parent continue */
				$start = $i + mb_strlen($inlineElement -> endTag);
				$remainder = mb_substr($text, $start, $len - $start);
				
				if($inlineElement -> hasArgs) {
					/* Handle arguments if needed */
					if($curKey == '') {
						array_push($arg, $buffer);
					} else {
						$arg[$curKey] = $buffer;
					}
					$buffer = self::$backend -> renderWithArgs($token, $arg);
				}
				
				/* Clean up and quit */
				$parsed .= $buffer; /* As far as I can tall $inPargraph should always be false here? */
				return array('parsed' => $parsed, 'remainder' => $remainder);
			}
			
			/* Next priority is looking for this element's agument tokens if applicable */
			if($inlineElement -> hasArgs && ($inlineElement -> argLimit == 0 || $inlineElement -> argLimit > count($arg))) {
				if(mb_strlen($inlineElement -> argSep) != 0 && $inlineElement -> argSep == mb_substr($text, $i, mb_strlen($inlineElement -> argSep))) {
					/* Hit argument separator */
					if($curKey == '') {
						array_push($arg, $buffer);
					} else {
						$arg[$curKey] = $buffer;
					}
					
					$curKey = ''; // Reset key
					$buffer = ''; // Reset parsed values
					/* Handle position properly */
					$i += mb_strlen($inlineElement -> argSep) - 1;
					$hit = true;
				} elseif($curKey == '' && mb_strlen($inlineElement -> argNameSep) != 0 && $inlineElement -> argNameSep == mb_substr($text, $i, mb_strlen($inlineElement -> argNameSep))) {
					/* Hit name/argument splitter */
					$curKey = $buffer; // Set key
					$buffer = '';  // Reset parsed values
					/* Handle position properly */
					$i += mb_strlen($inlineElement -> argNameSep) - 1;
					$hit = true;
				}
			}
				
			/* Looking for new open-tokens */
			$c = mb_substr($text, $i, 1);
			if(isset(self::$inlineLookup[$c])) {
				/* There are inline elements which start with this character. Check each one,.. */
				foreach(self::$inlineLookup[$c] as $key => $child) {
					if(!$hit && mb_strlen($child -> startTag) != 0 && $child -> startTag == mb_substr($text, $i, mb_strlen($child -> startTag))) {
						/* Hit a symbol. Parse it and keep going after the result */
						$start = $i + mb_strlen($child -> startTag);
						$remainder = mb_substr($text, $start, $len - $start);
						
						/* Regular, recursively-parsed element */
						$result = $this -> parseInline($remainder, $key);
						$buffer .= self::$backend -> encapsulateElement($key, $result['parsed']);
						
						$text = $result['remainder'];
						$len = mb_strlen($text);
						$i = -1;
						$hit = true;
					}
				}
			}

			if(!$hit) {
				if($c == "\n") {
					if(self::$tableStart -> startTag == mb_substr($text, $i + 1, mb_strlen(self::$tableStart -> startTag))) {
						$hit = true;
						$start = $i + 1 + mb_strlen(self::$tableStart -> startTag);
						$key = 'table';						
					} else {
						/* Check for non-table line-based stuff coming up next, each time \n is found */
						$next = mb_substr($text, $i + 1, 1);
						foreach(self::$lineBlock as $key => $block) {
							foreach($block -> startChar as $char) {
								if(!$hit && $next == $char) {
									$hit = true;
									$start = $i + 1;
									break 2;
								}
							}
						}
					}
					
					if($hit) {
						/* Go over what's been found */
						$remainder = mb_substr($text, $start, $len - $start);
						
						if($key == 'table') {
							$result = $this -> parseTable($remainder);
						} else {
							/* Let parseLineBlock take care of this on a per-line basis */
							$result = $this -> parseLineBlock($remainder, $key);
						}

						if($buffer != '') {
							/* Something before this was part of a paragraph */
							$parsed .= self::$backend -> encapsulateElement('paragraph', $buffer);
							$inParagraph == true;
						}
						$buffer = "";
						/* Now append this non-paragraph element */
						$parsed .= $result['parsed'];
							
						/* Same sort of thing as above */
						$text = $result['remainder'];
						$len = mb_strlen($text);
						$i = -1;
					}
						
					/* Other \n-related things if it wasn't as exciting as above */
					if($buffer != '' && !$hit) {
						/* Put in a line break if it is not going to be the first thing added. */
						$buffer .= "<br/>";
					}
				} else {
					/* Append character to parsed output if it was not part of some token */
					$buffer .= $c;
				}
			}
		}
		
		/* Need to throw argument-driven items at the backend first here */
		if($inlineElement -> hasArgs) {
			if($curKey == '') {
				array_push($arg, $buffer);
			} else {
				$arg[$curKey] = $buffer;
			}
			$buffer = self::$backend -> processArgs($token, $arg);
		}
		
		if($inParagraph && $buffer != '') {
			/* Something before this was part of a paragraph */
			$parsed .= self::$backend -> encapsulateElement('paragraph', $buffer);
		} else {
			$parsed .= $buffer;
		}
		
		return array('parsed' => $parsed, 'remainder' => '');
	}
	
	/**
	 * Parse block of wikitext known to be starting with a line-based token
	 * 
	 * @param $text Wikitext block to parse
	 * @param $token name of the LineBlock token which we suspect
	 */
	private function parseLineBlock($text, $token) {
		/* Block element we are using */
		$lineBlockElement = self::$lineBlock[$token];

		$lines = explode("\n", $text);
		/* Raw array of list items and their depth */
		$list = array();

		while(count($lines) > 0) {
			/* Loop through lines */
			$count = 0;
			$char = '';
			
			$count = self::countChar($lineBlockElement -> startChar, $lines[0], $lineBlockElement -> limit);
			if($count == 0) {
				/* This line is not part of the element, or is not valid */
				break;
			} else {
				$line = array_shift($lines);
				$char = mb_substr($line, $count - 1, 1);

				/* Slice off the lead-in characters and put through inline parser */
				$line = mb_substr($line, $count, mb_strlen($line) - $count);
				if(count($lineBlockElement -> endChar) > 0) {
					/* Also need to cut off end letters, such as in == Heading == */
					$endcount = self::countChar($lineBlockElement -> endChar, strrev($line), $lineBlockElement -> limit);
					$line = mb_substr($line, 0, mb_strlen($line) - $endcount);
				}
				$result = $this -> parseInline($line);
				$list[] = array('depth' => $count, 'item' => $result['parsed'], 'char' => $char);
			}
		}

		if($lineBlockElement -> nestTags) {
			/* Hierachy-ify nestable lists */
			$list = self::makeList($list);
		}
		$parsed = self::$backend -> renderLineBlock($token, $list);
		
		return array('parsed' => $parsed, 'remainder' => "\n". implode("\n", $lines));
	}
	
	/**
	 * Special handling for tables, uniquely containing both per-line and recursively parsed elements
	 * 
	 * @param string $text Text to parse
	 * @return multitype:string parsed and remaining text
	 */
	private function parseTable($text) {
		$parsed = '';
		$buffer = '';
		
		$lines = explode("\n", $text);
		$table['properties'] = array_shift($lines); /* get style="..." */
		$table['row'] = array();

		while(count($lines) > 0) {
			$line = array_shift($lines);
			if(trim($line) == self::$tableStart -> endTag) {
				/* End of table found */
				break;
			}

			$hit = false;
			foreach(self::$tableBlock as $token => $block) {
				/* Looking for matching per-line elements */
				if(!$hit && mb_strlen($block -> lineStart) != 0 && $block -> lineStart == mb_substr($line, 0, mb_strlen($block -> lineStart))) {
					$hit = true;
					break;
				}
			}

			if($hit) {
				/* Cut found token off start of line */
				$line =	mb_substr($line, mb_strlen($block -> lineStart), mb_strlen($line) - mb_strlen($block -> lineStart));
				
				if($token == 'td' || $token == 'th') {
					if(!isset($tmpRow)) {
						/* Been given a cell before a row. Make a row first */
						$tmpRow = array('properties' => '', 'col' => array());
					}

					/* Clobber the remaining text together and throw it to the cell parser */
					array_unshift($lines, $line);
					$result = $this -> parseTableCells($token, implode("\n", $lines), $tmpRow['col']);
					$lines = explode("\n", $result['remainder']);
					$tmpRow['col'] = $result['col'];
					
				} elseif($token == 'tr') {
					if(isset($tmpRow)) {
						/* Append existing row to table (if one exists) */
						$table['row'][] = $tmpRow;
					}
					/* Clearing current row and set properties */
					$tmpRow = array('properties' => $line, 'col' => array());
					$tmpRow['properties'] = $line;
				}
			}
		}

		if(isset($tmpRow)) {
			/* Tack on the last row */
			$table['row'][] = $tmpRow;
		}
		
		$parsed = self::$backend -> render_table($table);
		return array('parsed' => $parsed, 'remainder' => "\n". implode("\n", $lines));
	}

	/**
	 * Retrieve columns started in this line of text
	 * 
	 * @param string $token Type of cells we are looking at (th or td)
	 * @param string $text Text to parse
	 * @param string $colsSoFar Columns which have already been found in this row
	 * @return multitype:string parsed and remaining text
	 */
	private function parseTableCells($token, $text, $colsSoFar) {
		$tableElement = self::$tableBlock[$token];
		$len = mb_strlen($text);
		
		$tmpCol = array('arg' => array(), 'content' => '', 'token' => $token);	
		$argCount = 0;
		$buffer = '';

		/* Loop through each character */
		for($i = 0; $i < $len; $i++) {
			$hit = false;
			// TODO: spot inline and lineBlock elements
			
			if($tableElement -> inlinesep == mb_substr($text, $i, mb_strlen($tableElement -> inlinesep))) {
				/* Got column separator, so this column is now finished */
				$tmpCol['content'] = $buffer;
				$colsSoFar[] = $tmpCol;
				
				/* Reset for the next */
				$tmpCol = array('arg' => array(), 'content' => '', 'token' => $token);
				$buffer = '';
				$hit = true;
				$i += mb_strlen($tableElement -> inlinesep) - 1;		
				$argCount = 0;
			}
			
			if(!$hit && $argCount < ($tableElement -> limit) && $tableElement -> argsep == mb_substr($text, $i, mb_strlen($tableElement -> argsep))) {
				/* Got argument separator. Shift off the last argument */
				$tmpCol['arg'][] = $buffer;
				$buffer = '';
				$hit = true;
				$i += mb_strlen($tableElement -> argsep) - 1;
				$argCount++;
			}
			
			if(!$hit) {
				$c = mb_substr($text, $i, 1);
				if($c == "\n") {
					/* Checking that the next line isn't starting a different element of the table */
					foreach(self::$tableBlock as $key => $block) {
						if($block -> lineStart == mb_substr($text, $i + 1, mb_strlen($block -> lineStart))) {
							/* Next line is more table syntax. bail otu and let something else handle it */
							break 2;
						}
					}
				}
				$buffer .= $c;
			}
		}
		
		/* Put remaining buffers in the right place */
		$tmpCol['content'] = $buffer;
		$colsSoFar[] = $tmpCol;
		$start = $i + 1;
		$remainder = mb_substr($text, $start, $len - $start);
		
		return array('col' => $colsSoFar, 'remainder' => $remainder);
	}
	
	/**
	 * Count the number of times a character occurs at the start of a string
	 * 
	 * @param string $char character to check
	 * @param string $text String to search
	 * @return number The number of times this character repeats at the start of the string
	 */
	private static function countChar($chars, $text, $max = 0) {
		for($i = 0; $i < mb_strlen($text) && ($max == 0 || $i <= $max); $i++) {
			$c = mb_substr($text, $i, 1);
			/* See if this char is a valid start */
			$match = false;
			foreach($chars as $char) {
				$match = $match || ($char == $c);
			}
			
			if(!$match) {
				return $i;
			}
		}

		if(!($max == 0 || $i <= $max)) {
			/* max was reached */
			return $max;
		}

		/* Otherwise looks like the entire string is just this character repeated.. */
		return mb_strlen($text);
	}
	
	/**
	 * Create a list from what we found in parseLineBlock(), returning all elements.
	 */
	private static function makeList($lines) {
		$list = self::findChildren($lines, 0, -1);
		return $list['child'];
	}
	
	/**
	 * Recursively nests list elements inside eachother, forming a hierachy to traverse when rendering
	 */
	private static function findChildren($lines, $depth, $minKey) {
		$children	= array();
		$not		= array();
		
		foreach($lines as $key => $line) {
			/* Loop through for candidates */
			if($key > $minKey) {
				if($line['depth'] > $depth) {
					$children[$key] = $line;
					unset($lines[$key]);
				} elseif($line['depth'] <= $depth) {
					break;
				}
			}
		}
		
		/* For each child, list its children */
		foreach($children as $key => $child) {
			if(isset($children[$key])) {
				$result = self::findChildren($children, $child['depth'], $key);
				$children[$key]['child'] = $result['child'];
				
				/* We know that all of this list's children are NOT children of this item (directly), so remove them from our records. */
				foreach($result['child'] as $notkey => $notchild) {
					unset($children[$notkey]);
					$not[$notkey] = true;
				}
				
				/* And same for non-direct children reported above */
				foreach($result['not'] as $notkey => $foo) {
					unset($children[$notkey]);
					$not[$notkey] = true;
				}
			}
		}

		return array('child' => $children, 'not' => $not);
	}
	
	/**
	 * Remove blocks of text surrounded by a defined tag, storing them elsewhere for processing
	 * 
	 * @param string $key The name of the block to use, used to find out what tags we are using, and to store the removed elements.
	 * @param string $text The text that is being parsed.
	 * @param boolean $store Set to true if this block is to be tracked inside the $markupBlob variable
	 */
	private function stripBlock($key, $text, $store) {
		$block = self::$block[$key];
		$markupBlob[$key] = array();
		
		// TODO: Loop through $text and remove tags here
		
		return $text;
	}
}

/**
 * Defines high-level block elements which store different blocks of markup, which may or may not be wikitext. Should not be nested.
 */
class ParserBlockElement {
	public $startTag, $endTag;

	function ParserBlockElement($startTag, $endTag) {
		$this -> startTag = $startTag;
		$this -> endTag = $endTag;
	}
}

/**
 * Stores inline elements
 */
class ParserInlineElement {
	public $startTag, $endTag;
	public $argSep, $argNameSep;
	public $hasArgs;

	function ParserInlineElement($startTag, $endTag, $argSep = '', $argNameSep = '', $argLimit = 0) {
		$this -> startTag = $startTag;
		$this -> endTag = $endTag;
		$this -> argSep = $argSep;
		$this -> argNameSep = $argNameSep;
		$this -> argLimit = $argLimit;
		$this -> hasArgs = $this -> argSep != '';
	}
}

class ParserLineBlockElement {
	public $startChar;	/* Characters which can loop to start this element */
	public $endChar;	/* End character */
	public $limit;		/* Max depth of the element */
	public $nestTags;	/* True if the tags for this element need to made hierachical for nesting */
	
	function ParserLineBlockElement($startChar, $endChar, $limit = 0, $nestTags = true) {
		$this -> startChar = $startChar;
		$this -> endChar = $endChar;
		$this -> limit = $limit;
		$this -> nestTags = $nestTags;
	}
}

class ParserTableElement {
	public $lineStart;	/* Token appearing at start of line */
	public $argsep;
	public $limit;
	public $inlinesep;
	
	function ParserTableElement($lineStart, $argsep, $inlinesep, $limit) {
		$this -> lineStart = $lineStart;
		$this -> argsep = $argsep;
		$this -> inlinesep = $inlinesep;
		$this -> limit = $limit;
	}
}

/**
 * Methods from this class are called as different types of markup are encountered,
 * and are expected to provide supporting functions like template substitutions,
 * link destinations, and other installation-specific oddities
 */
class DefaultParserBackend {

	/**
	 * Process an element which has arguments. Links, lists and templates fall under this category
	 * 
	 * @param string $elementName
	 * @param string $arg
	 */
	public function renderWithArgs($elementName, $arg) {
		$fn = 'self::render_'.$elementName;
		
		if(is_callable($fn)) {
			/* If a function is defined to handle this, use it */
			return call_user_func_array($fn, array($arg));
		} else {
			return $arg[0];
		}
	}
	
	/**
	 * Encapsulate inline elements
	 * 
	 * @param string $text parsed text contained within this element
	 * @param string $elementName the name of the element
	 * @return string Correct markup for this element
	 */
	public function encapsulateElement($elementName, $text) {
		$fn = 'self::encapsulate_'.$elementName;
		
		if(is_callable($fn)) {
			/* If a function is defined to encapsulate this, use it */
			return call_user_func_array($fn, array($text));
		} else {
			return $text;
		}
	}
	
	public function renderLineBlock($elementName, $list) {
		$fn = 'self::render_'.$elementName;
		
		if(is_callable($fn)) {
			/* If a function is defined to encapsulate this, use it */
			return call_user_func_array($fn, array($elementName, $list));
		} else {
			return $elementName;
		}
	}
	
	public function render_ol($token, $list) {
		return $this -> render_list($token, $list);
	}
	
	public function render_ul($token, $list) {
		return $this -> render_list($token, $list);
	}
	
	public function render_dl($token, $list) {
		return $this -> render_list($token, $list);
	}
	
	public function render_h($token, $headings) {
		$outp = "";
		foreach($headings as $heading) {
			$tag = "h" . $heading['depth'];
			$outp .= "<$tag>".$heading['item']."</$tag>\n";
		}
		return $outp;
	}
	
	public function render_pre($token, $lines) {
		$outpline = array();
		foreach($lines as $line) {
			$outpline[] = $line['item'];
		}
		
		return "<pre>".implode("\n", $outpline)."</pre>";
	}
	
	/**
	 * Render list and any sub-lists recursively
	 * 
	 * @param string $token The type of list (expect ul, ol, dl)
	 * @param mixed $list The hierachy representing this list
	 * @return string HTML markup for the list
	 */
	public function render_list($token, $list, $expectedDepth = 1) {
		$outp = '';
		$subtoken = "li";
		$outp .= "<$token>\n";
		
		foreach($list as $item) {
			if($token == 'dl') {
				$subtoken = $item['char'] == ";"? "dt": "dd";
			}
			$outp .= "<$subtoken>";
			$diff = $item['depth'] - $expectedDepth;
			/* Some items are undented unusually far ..  */
			if($diff > 0) {
				$outp .= str_repeat("<$token><$subtoken>", $diff);
			}
			/* Caption of this item */
			$outp .= $item['item'];
			if(count($item['child']) > 0) {
				/* Add children if applicable */
				$outp .= $this -> render_list($token, $item['child'], $item['depth'] + 1);
			}
			if($diff > 0) {
				/* Close above extra encapsulation if applicable */
				$outp .= str_repeat("</$subtoken></$token>", $diff);
			}
			$outp .= "</$subtoken>\n";
										
		}
		$outp .= "</$token>\n";
		return $outp;
	}
	
	/**
	 * Default rendering of [http://... link] or [http://foo]
	 * 
	 * @param string $destination page name we are linking to
	 * @param string $caption Caption of this link (can inlude parsed wikitext)
	 * @return string HTML markup for the link
	 */
	public function render_a_internal($arg) {
		$caption = $destination = $arg[0];
		if(isset($arg[1])) {
			$caption = $arg[1];
		}
		return "<a href=\"".htmlspecialchars($destination)."\">".$caption."</a>";
	}
	
	/**
	 * Default rendering of [[link]] or [[link|foo]]
	 *
	 * @param string $destination page name we are linking to
	 * @param string $caption Caption of this link (can inlude parsed wikitext)
	 * @return string HTML markup for the link
	 */
	public function render_a_external($arg) {
		$caption = $destination = $arg[0];
		if(isset($arg[1])) {
			$caption = $arg[1];
		}
		return "<a href=\"".htmlspecialchars($destination)."\" class=\"external\">".$caption."</a>";
	}
	
	/**
	 * Default encapsulation for '''bold'''
	 * 
	 * @param string $text Text to make bold
	 * @return string
	 */
	public function encapsulate_bold($text) {
		return "<b>".$text."</b>";
	}
	
	/**
	 * Default encapsulation for ''italic''
	 *
	 * @param string $text Text to make bold
	 * @return string
	 */
	public function encapsulate_italic($text) {
		return "<i>".$text."</i>";
	}
	
	public function encapsulate_paragraph($text) {
		return "<p>".$text."</p>\n";
	}
	
	/**
	 * Generate HTML for a table
	 */
	public function render_table($table) {
		if($table['properties'] == '') {
			$outp = "<table>\n";
		} else {
			$outp = "<table ".trim($table['properties']).">\n";
		}
		
		foreach($table['row'] as $row) {
			$outp .= $this -> render_row($row);
		}
		
		return $outp."</table>\n";
	}
	
	/**
	 * Render a single row of a table
	 */
	public function render_row($row) {
		/* Show row with or without attributes */
		if($row['properties'] == '') {
			$outp = "<tr>\n";
		} else {
			$outp = "<tr ".trim($row['properties']).">\n";
		}

		foreach($row['col'] as $col) {
			/* Show column with or without attributes */
			if(count($col['arg']) != 0) {
				$outp .= "<". $col['token']. " " . trim($col['arg'][0]) . ">";
			} else {
				$outp .= "<". $col['token']. ">";
			}
			$outp .= $col['content']."</". $col['token']. ">\n";
		}
		
		return $outp . "</tr>\n";
	}
	
	public function render_template($arg) {
		return "(Template '" . $arg[0]."')";
	}
}
?>
