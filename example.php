<?php

// error_reporting(E_ALL);
// ini_set("display_errors", "1");

// Require the plugin class
require "ASearch.php";

// Database & table info
$host = "localhost";
$database = "crm";
$user = "root";
$password = "bijoux22";
$table = "users";

// Create a database connection w/ PDO
$pdo = new PDO('mysql:host='.$host.';dbname='.$database.';charset=utf8', $user, $password);

// Must be called before the construtor if using oracle
ASearch::useOracle();

// Set up advanced search object
$ASearch = new ASearch($table, $pdo);

// Change this to list columns in YOUR table that you DON'T need
$ASearch->omitCols(array("vid", "password", "adf_email", 
	'phone','aphone','cphone','hours','photo1','photo2',
	'lat','long','sig','letters','letterhead','time_offset',
	'print_reminder','ct_email','rcid','recs_pw',
	'pc','ref','imgopts','email','admin'));

// Add a condition
// in this example we'll make sure only non-admins are returned in the results
$ASearch->addCondition("admin = 'no'");

// This line handles all ajax stuff for the page
$ASearch->checkAJAX();

// Let the plugin know which JS function to send the results to
$ASearch->setJSHandler("HandleSearchResults");

?><!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title>ASearch Minimal Example</title>
		
		<!-- jQuery UI CSS, required for plugin -->
		<link href="//code.jquery.com/ui/1.11.4/themes/black-tie/jquery-ui.css" rel="stylesheet" />
		
		<!-- DataTables CSS, not required for plugin but used in this example -->
		<link href="//cdn.datatables.net/1.10.11/css/jquery.dataTables.min.css" rel="stylesheet" />
		
		<!-- Plugin CSS -->
		<?php  echo $ASearch->getCSS(); ?>
		
    </head>
    <body>
		
		<!-- Plugin HTML -->
		<?php echo $ASearch->getHTML(); ?>
		
		<!-- We will use this in our HandleSearchResults() function -->
		<div id='res'></div>
		
		<!-- jQuery and jQuery UI are required for the plugin to work -->
		<script src="//code.jquery.com/jquery-1.12.3.min.js"></script>
		<script src='//code.jquery.com/ui/1.11.4/jquery-ui.min.js'></script>
		
		<!-- 
			The plugin does not require DataTables, but we will use it to
			show our search results in this example. 
		-->
		<script src='//cdn.datatables.net/1.10.11/js/jquery.dataTables.min.js'></script>
		
		<!-- Plugin Javascript -->
		<?php echo $ASearch->getJS(); ?>
		
		<script>
			/**
			 * This is the function we set as our handler when 
			 * we did $ASearch->setJSHandler("HandleSearchResults");
			 * The plugin will call this function to update the 
			 * search results.
			 */
			function HandleSearchResults(res){
				var html = makeTable(res);
				$("#res").empty().html(html);
				$("#resTable").DataTable();
			}
			
			/**
			 * A helper function that creates table markup
			 * @param array of objects r
			 */
			function makeTable(r){
				var html = [];
				html.push("<br><table id='resTable'><thead><tr>");
				for(var p in r[0])
					if(r[0].hasOwnProperty(p))
						html.push("<th>"+p+"</th>");
				html.push("</tr></thead><tbody>");
				for(var i=0; i<r.length; i++){
					html.push("<tr>");
					for(var  p in r[i])
						if(r[i].hasOwnProperty(p))
							html.push("<td>"+r[i][p]+"</td>");
					html.push("</tr>");
				}
				html.push("</tbody></table>");
				return html.join("");
			}
		</script>
    </body>
</html>
