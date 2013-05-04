<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8" />
<style>
	/* "Images" section of MediaWiki vector.css for rendering the output here */
	
	/* @noflip */ div.tright,
	div.floatright,
	table.floatright {
		clear: right;
		float: right;
	}
	/* @noflip */ div.tleft,
	div.floatleft,
	table.floatleft {
		float: left;
		clear: left;
	}
	div.floatright,
	table.floatright,
	div.floatleft,
	table.floatleft {
		position: relative;
	}
</style>
</head>
<body>

<?
# Version of SimpleTest to open in a web-browser
require_once("../../wikitext.php");
$input = file_get_contents("input.txt");

WikitextParser::init();
$parser = new WikitextParser($input);
echo $parser -> result;

?>
</body>
</html>
