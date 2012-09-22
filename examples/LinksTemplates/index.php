<?
require_once("../../WikitextParser.php");

/**
 * The custom behaviour of templates and links needs to be defined here:
 */
class CustomParserBackend extends DefaultParserBackend {
	
	public function getInternalLinkInfo($info) {
		/* Take people to the right place */
		$info['dest'] = "index.php?page=".$info['dest'];
		return $info;
	}
	

}

/* Figure out what page to load */
$pageName = "home";
if(isset($_REQUEST['page'])) {
	$pageName = $_REQUEST['page'];
}
if(!$input = getPageText($pageName)) {
	/* 404 text */
	$input = "== 404 Not found ==\n Page not found. Return to [[home]].";
} else {
	$input = "== $pageName ==\n".$input;
}

/* Parse it and print it */
WikitextParser::init();
WikitextParser::$backend = new CustomParserBackend;
$parser = new WikitextParser($input);
echo $parser -> result;

/**
 * Get wikitext for a given page
 * 
 * @param   string $pageName   Identifier for the page name
 * @return  boolean|string     Text of the page, or false if it could not be found
 */
function getPageText($pageName) {
	$fn = "page/" . urlencode($pageName) . ".txt";
	if(!file_exists($fn)) {
		return false;
	} else {
		return file_get_contents($fn);
	}
}
?>
