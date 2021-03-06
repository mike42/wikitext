<?php
namespace Example;

require_once(__DIR__ . "/../../vendor/autoload.php");
use Mike42\Wikitext\DefaultParserBackend;
use Mike42\Wikitext\WikitextParser;

/**
 * Example of how you might want to encapsulate a page-renderer to make use of the parser.
 *
 * We provide new hooks to create internal links and load templates.
 */
class LinksTemplates extends DefaultParserBackend
{
    public function getInternalLinkInfo($info)
    {
        /* Take people to the right place */
        $info['dest'] = "index.php?page=".$info['dest'];
        return $info;
    }
    
    public function getTemplateMarkup($template)
    {
        return LinksTemplates::getPageText($template);
    }
    
    public static function showPage($pageName)
    {
        if (!$input = self::getPageText($pageName)) {
            /* 404 text */
            $input = "= 404 Not found =\nPage not found. Return to [[home]].";
        } else {
            $input = "= $pageName =\n".$input;
        }
        /* Parse it and print it */
        WikitextParser::$backend = new LinksTemplates();
        $parser = new WikitextParser($input);
        echo $parser -> result;
        echo "<hr /><b>Markup for this page:</b><pre>".htmlentities($input)."</pre>";
        echo "<b>After preprocessing:</b><pre>".htmlentities($parser -> preprocessed)."</pre>";
    }

    /**
     * Get wikitext for a given page
     *
     * @param   string $pageName   Identifier for the page name
     * @return  boolean|string     Text of the page, or false if it could not be found
     */
    public static function getPageText($pageName)
    {
        $fn = "page/" . urlencode($pageName) . ".txt";
        if (!file_exists($fn)) {
            return false;
        } else {
            return file_get_contents($fn);
        }
    }
}

/* Figure out what page to load */
$pageName = "home";
if (isset($_REQUEST['page'])) {
    $pageName = $_REQUEST['page'];
}
LinksTemplates::showPage($pageName);
