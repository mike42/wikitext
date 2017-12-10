<?php
require_once(__DIR__ . "/../../vendor/autoload.php");
use Mike42\Wikitext\WikitextParser;
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8" />
<style>
/* "Images" section of MediaWiki vector.css for rendering the output here */

/* @noflip */
div.tright,div.floatright,table.floatright {
    clear: right;
    float: right;
}
/* @noflip */
div.tleft,div.floatleft,table.floatleft {
    float: left;
    clear: left;
}

div.floatright,table.floatright,div.floatleft,table.floatleft {
    position: relative;
}
</style>
</head>
<body>

    <?php
    # Version of SimpleTest to open in a web-browser
    $input = file_get_contents("input.txt");

    $parser = new WikitextParser($input);
    echo $parser -> result;

    ?>
</body>
</html>
