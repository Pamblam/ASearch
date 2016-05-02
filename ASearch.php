<?php

/**
 * A class for doing advances searches 
 * on a table or view in Oracle DB
 */
class ASearch{
	
	############################################################################
	## Properties ##############################################################
	############################################################################
	
	/**
	 * PDO resource
	 * @var PDO Resource
	 */
	private static $pdo;
	
	/**
	 * "MYSQL" or "ORACLE"
	 * @var string 
	 */
	private static $DBMS = "MYSQL";
	
	/**
	 * Name of the table or view to search in
	 * @var string
	 */
	private $table_or_view;
	
	/**
	 * Column information
	 * @var array
	 */
	private $cols;
	
	/**
	 * Array of errors thrown
	 * @var array
	 */
	private $errors;
	
	/**
	 * Keep track of when the JS is printed
	 * @var bool 
	 */
	private $JSPrinted = false;
	
	/**
	 * The Javascript function that will handle the search results.
	 * @var string 
	 */
	private $JSHandler = "";
	
	/**
	 * Maximum number of rows to return from the DB
	 * @var int
	 */
	private $RowLimit = 500;
	
	/**
	 * Set of conditions used to limit search results
	 * @var array
	 */
	private $conditions = array();
	
	############################################################################
	## Public Methods ##########################################################
	############################################################################
	
	/**
	 * Constructor
	 * @param string $table_or_view
	 * @param PDO Resource $pdo
	 */
	public function __construct($table_or_view, $pdo=null){
		if(strpos($table_or_view, " ") !== false) 
			$table_or_view = "(".$table_or_view.") ast";
		if(headers_sent()) 
			$this->error("ASearch must be initialised before any headers are sent.");
		if(!empty($pdo)) self::$pdo = $pdo;
		if(empty(self::$pdo)) 
			$this->error("No database connection provided", true);
		$this->table_or_view = $table_or_view;
		$this->errors = array();
		$this->loadColumns();
	}
	
	/**
	 * Call this function if using Oracle instead of MySQL
	 */
	public static function useOracle(){
		self::$DBMS = "ORACLE";
	}
	
	/**
	 * Add a condition that will limit search results
	 * @param string $condition
	 */
	public function addCondition($condition){
		$this->conditions[] = $condition;
	}
	
	/**
	 * Omit given columns from the search criteria
	 * @param array $cols
	 */
	public function omitCols($cols){
		$c = array();
		foreach($this->cols as $col) 
			if(!in_array(strtoupper($col), array_map("strtoupper", $cols))) 
				array_push($c, $col);
		$this->cols = $c;
	}
	
	/**
	 * Set a new PDO connection
	 * @param string $dsn
	 * @param sting $user
	 * @param string $pass
	 */
	public static function setPDO($dsn, $user, $pass){
		try{
			self::$pdo = new PDO($dsn, $user, $pass);
		} catch (PDOException $ex) {
			$this->error("Could not connect to PDO.");
		}
	}
	
	/**
	 * Get all column info for the given table
	 * @return array
	 */
	public function getColumns(){
		return $this->cols;
	}
	
	/**
	 * Set the maximum number of rows to be returned
	 * @param int $limit
	 */
	public function setRowLimit($limit){
		$this->RowLimit = intval($limit);
	}
	
	/**
	 * Print form CSS
	 * @param $tags bool - include <style> tags?
	 * @return string
	 */
	public function getCSS($tags = true){
		ob_start(); ?>
		<style>
			.ASearchDiv{
				background: #E8E8E8;
				border-radius: 1em;
				border:1px solid #D9D9D9;
				padding: .5em;
			}
			.ASResItem{
				font-size: 90%;
				background: #CCF2FF;
				border-radius: .5em;
				border:1px solid #A8E9FF;
				padding: .175em;
				margin-right: .75em;
				margin-top: .75em;
				display: inline-block;
			}
			.ASearchDiv span{ font-weight: bold; }
			.ASearchOp, .ASearchVals, .ASdone, #ASearchLoading, .ASConjunction{ display:none; }
			#ASearchCritera{ 
				border-top: 1px solid black; 
				display: none;
				padding-top:.5em;
			}
			.closeCriteria{
				cursor: pointer;
			}
		</style>	
		<?php 
		return $tags ? 
			ob_get_clean() :
			str_replace("<style>", "", str_replace("</style>", "", ob_get_clean()));
	}
	
	/**
	 * Print the search form view
	 * @return string
	 */
	public function getHTML(){
		ob_start(); ?>
		<div class="ASearchDiv">
			<span>Add a Filter: </span>
			<span class="ASparamcontainer">
				<select class='ASConjunction'><option value='AND'>AND</option><option value='OR'>OR</option></select>
				<select class="ASearchCol">
					<option>Column name</option>
					<?php foreach($this->cols as $col) echo "<option value='$col'>$col</option>\n"; ?>
				</select>
				<select class='ASearchOp'><option value='equals'>Equal</option><option value='contains'>contains</option><option value='does not equal'>Not Equal</option></select>
				<input class='ASearchVals'>
				<input type="button" value="Ok" class="ASdone">
				<img id='ASearchLoading' src="data:image/gif;base64,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" />
				<div id="ASearchCritera"></div>
			</span>
		</div>
		<?php return ob_get_clean();
	}
	
	/**
	 * Print the page Javascript
	 * @param $tags bool - include <script> tags?
	 * @return string
	 */
	public function getJS($tags = true){
		$this->JSPrinted = true;
		if(empty($this->JSHandler)) die("<script>window.alert('ASearch Error: You must call setJSHandler() before calling getJS()');</script>");
		ob_start(); ?>
		<script>
			(function($){
				$(document).ready(function(){
					// Make sure jQuery is available
					if(undefined === $) return alert("jQuery is required for ASearch to function.");
					if(undefined === $.ui) return alert("jQuery UI is required for ASearch to function.");
					// The chosen search critera
					var SearchCriteria = [];
					// Handle dropdown change
					$(document).on("change", ".ASearchCol", function(){
						var currentBlock = $(this).parent()[0];
						// Make sure this has a value
						if(!$(this).val()) return;
						// Get the chosen column name
						var colname = $(this).val();
						// Do ajax call to get the unique values in the column
						$("#ASearchLoading").show();
						$(currentBlock).find(".ASearchVals").val('').hide();
						$(currentBlock).find(".ASearchOp").hide()[0].selectedIndex = 0;;
						$(currentBlock).find(".ASdone").hide();
						$.ajax({
							type: "POST",
							data: {ASaction: "getVals", column: colname},
							url: window.location
						}).done(function(resp){
							$("#ASearchLoading").hide();
							if(!resp.success) return alert(resp.message);
							$(currentBlock).find(".ASearchVals").show();
							$(currentBlock).find(".ASearchOp").show();
							$(currentBlock).find(".ASdone").show();
							$(currentBlock).find(".ASearchVals").autocomplete({source: resp.data});
						});
					});
					// Get search results
					function getASResults(){
						$(".ASearchCol")[0].selectedIndex = 0;
						$(".ASearchVals").val('').hide();
						$(".ASearchOp").hide()[0].selectedIndex = 0;
						$(".ASdone").hide();
						$("#ASearchCritera").empty();
						var firstShown = false;
						for(var i=0; i<SearchCriteria.length; i++){
							if(undefined !== SearchCriteria[i]){
								$("#ASearchCritera").append("<span class='ASResItem'>"+(SearchCriteria[i].conjunction && firstShown ? "<u>"+SearchCriteria[i].conjunction+"</u> " : "")+"<i>"+SearchCriteria[i].column+"</i> "+SearchCriteria[i].operator+" \""+SearchCriteria[i].value+"\" <img class='closeCriteria' data-index='"+i+"' src='data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAwAAAAMCAMAAABhq6zVAAAACXBIWXMAAAsTAAALEwEAmpwYAAAAB3RJTUUH1gUdFC83067DbgAAAIdQTFRF////704I71EE8FAI8FQI8FgI8VUI8VgI8VgM9mUA9nca9ok495lS+I1C+I9G+JhR+J5c+Kht+Lii+Z1b+a93+baD+2IA+2QD+3AX/IEy/WIA/W0M/XMW/YI0/YU5/atv/bR//bmJ/byN/b2P/cCV/ruN/ryO/sii/tGy/tm//tzE/t/K////tpAMcwAAAAF0Uk5TAEDm2GYAAAABYktHRCy63XGrAAAAY0lEQVQI11XNQQ6DMBAEwZn1GhsLKQr/fyXCMgloJ6ccXA/oBiY7k+fsiTsIe3ncsvwc4VjeLU5t6bwuR4ycF6XvCBiorrqqizAg9G8ZxMbe2Sg4rJbng1KqgbCVIdA0YppOfkgQJaDWSNTwAAAAAElFTkSuQmCC' /></span>");
								firstShown = true;
							}
						}
						if($(".ASResItem").length) $(".ASConjunction").show();
						$("#ASearchLoading").show();
						$.ajax({
							type: "POST",
							data: {ASaction: "getResults", criteria: SearchCriteria},
							url: window.location
						}).done(function(resp){
							$("#ASearchLoading").hide();
							<?php echo $this->JSHandler; ?>(resp.data);
						});
					}
					// Handle changes of the search criteria
					$(document).on("change", ".ASearchVals", function(){
						SearchCriteria.push({
							column: $(".ASearchCol").val(),
							operator: $(".ASearchOp").val(),
							value: $(".ASearchVals").val(),
							conjunction: $(".ASResItem").length ? $(".ASConjunction").val() : null
						});
						$("#ASearchCritera").show();
						getASResults();
					});
					// Delete a criteria
					$(document).on("click", ".closeCriteria", function(){
						var index = $(this).data("index");
						delete SearchCriteria[index];
						$(this).parent().remove();
						if(!$(".ASResItem").length){
							$(".ASConjunction").hide();
							$("#ASearchCritera").hide();
						}
						getASResults();
					});
				});
			})(jQuery);
		</script>
		<?php 
		return $tags ? 
			ob_get_clean() :
			str_replace("<script>", "", str_replace("</script>", "", ob_get_clean()));
	}
	
	/**
	 * Set the Javascript handler
	 * @param string $jsHandler
	 */
	public function setJSHandler($jsHandler){
		if($this->JSPrinted) die("<script>window.alert('ASearch Error: You must call setJSHandler() before calling getJS()');</script>");
		$this->JSHandler = $jsHandler;
	}
	
	/**
	 * Check for ajax calls
	 */
	public function checkAJAX(){
		if(empty($_POST['ASaction'])) return;
		$return = array("success"=>true, "message"=>"Success!", "data"=>array());
		$limit = self::$DBMS == "MYSQL" ? "LIMIT ".$this->RowLimit : "AND ROWNUM < ".$this->RowLimit;
		try{
			switch($_POST['ASaction']){
				// Get unique column values
				case "getVals":
					if(empty($_POST['column'])){
						$return['success'] = false;
						$return['message'] = "The 'column' paramter is missing from your request.";
						break;
					}
					if(!in_array($_POST['column'], $this->cols)){
						$return['success'] = false;
						$return['message'] = "This column ({$_POST['column']}) does not exist or is not available.";
						break;
					}
					try{
						$q = self::$pdo->query("SELECT DISTINCT {$_POST['column']} FROM {$this->table_or_view} WHERE 1=1 $limit");
						if(!$q){
							$return['message'] = "Unable to get fetch columns for {$_POST['column']}.";
							$return['success'] = false;
						}
						while($res = $q->fetch(PDO::FETCH_ASSOC))
							if(NULL != $res) $return['data'][] = (String) $res[$_POST['column']];
					}catch(PDOException $e){
						$return['message'] = $e->getMessage();
						$return['success'] = false;
					}
					break;
				case "getResults":
					if(empty($_POST['criteria'])){
						$return['success'] = false;
						$return['message'] = "The 'column' paramter is missing from your request.";
						break;
					}
					$conditions = $this->buildConditionals();
					$cols = self::$DBMS == "MYSQL" ? 
						"`".implode('`, `', $this->cols)."`" : 
						implode(', ', $this->cols);
					$sql = "SELECT $cols FROM {$this->table_or_view} WHERE 1=1 AND ";
					$first = true; $lastConjunction = "";
					foreach($_POST['criteria'] as $c){
						if(empty($c)) continue;
						if(!$first) $sql .= $c['conjunction']." ";
						$sql .= self::$DBMS == "MYSQL" ? "`{$c['column']}` " : $c['column']." ";
						switch(strtolower($c['operator'])){
							case "contains": $sql .= "LIKE '%{$c['value']}%' "; break;
							case "does not equal": $sql .= "!= '{$c['value']}' "; break;
							default: $sql .= "= '{$c['value']}' "; break;
						}
						$lastConjunction = $c['conjunction'];
						$first = false;
					}
					$sql = trim(substr($sql, 0, strlen($sql)+1));
					$sql .= " $conditions $limit";
					try{
						$q = self::$pdo->query($sql);
						if(!$q){
							$return['message'] = "Unable to get fetch data.";
							$return['success'] = false;
						}
						$return['data'] = $q->fetchAll(PDO::FETCH_ASSOC);
					}catch(PDOException $e){
						$return['message'] = $e->getMessage();
						$return['success'] = false;
					}
					break;
			}
		}catch(Exception $e){
			$return['success'] = false;
			$return['message'] = $e->getMessage();
		}
		header("Content-Type: application/json");
		echo json_encode($return);
		exit;
	}
	
	############################################################################
	## Private Methods #########################################################
	############################################################################
	
	/**
	 * Build the part of the query that will make limitations
	 */
	private function buildConditionals(){
		if(empty($this->conditions)) return "";
		return "AND (".implode(" AND ", $this->conditions).")";
	}
	
	/**
	 * Load column information
	 */
	private function loadColumns(){
		$limit = self::$DBMS == "MYSQL" ? "LIMIT 1" : "WHERE ROWNUM = 1";
		try{
			// Get column names
			$Q = self::$pdo->query("SELECT * FROM {$this->table_or_view} $limit");
			$cols = $Q->fetch(PDO::FETCH_ASSOC);
			if(empty($cols)) $this->error("Not able to gather column data from {$this->table_or_view}");
			$this->cols = array_keys($cols);
		} catch (PDOException $ex) {
			$this->error($ex->getMessage());
		}
	}
	
	/**
	 * Add an error message
	 * @param string $message
	 * @param bool $dump
	 */
	private function error($message, $dump=false){
		array_push($this->errors, $message);
		if($dump) $this->dumpErrors();
	}
	
	/**
	 * Show the errors
	 */
	private function dumpErrors(){
		echo "<table>";
		foreach($this->errors as $e){
			echo "<tr><td>$e</td></tr>";
		}
		echo "<table>";
	}
}