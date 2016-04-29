<?php

require "ASearch.php";

// Set up advanced search object, remove some columns we don't need
$ASearch = new ASearch("myTable", $pdo);
$ASearch->omitCols(array("id"));

// This line handles all ajax stuff for the page
$ASearch->checkAJAX();

// Let the plugin know which JS function to send the results to
$ASearch->setJSHandler("HandleSearchResults");

?><!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title>ASearch Example</title>
		<link href="//code.jquery.com/ui/1.11.4/themes/black-tie/jquery-ui.css" rel="stylesheet" />
		<?php 
			// Print the plugin CSS
			echo $ASearch->getCSS(); 
		?>
    </head>
    <body>
		<?php 
			// Print ASearch form
			echo $ASearch->getHTML();
		?>
		<div id='res'></div>
		<script src="//code.jquery.com/jquery-1.12.3.min.js"></script>
		<script src='//code.jquery.com/ui/1.11.4/jquery-ui.min.js'></script>
		<?php 
			// Print the plugin JS
			echo $ASearch->getJS(); 
		?>
		<script>
			function HandleSearchResults(res){
				$("#res").html(JSON.stringify(res));
			}
		</script>
    </body>
</html>
