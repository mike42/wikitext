<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8" />
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
