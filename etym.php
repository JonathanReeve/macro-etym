<!DOCTYPE html> 
<html lang="en">
<head> 

<meta charset="utf-8">
<link href='http://fonts.googleapis.com/css?family=Rokkitt:400,700' rel='stylesheet' type='text/css'> 
<link rel="stylesheet" href="style.css"> 
<link href="//netdna.bootstrapcdn.com/font-awesome/3.2.1/css/font-awesome.css" rel="stylesheet"> 

<script src="js/jquery-1.10.2.min.js"></script> 

<script> 
$(document).ready(function(){ 
	//jQuery code here

	$(".box > h2").click(function(){ 
		$(this).next().slideToggle(); 
		$(this).toggleClass('closed'); 
	}); 

	//end jQuery code
});
</script> 

</head>
<body> 
<div id="container"> 

<h1>Macro-Etymological Analyzer</h1> 

<p class="padding">&nbsp; &nbsp;</p> <!-- fix for absolutely-positioned h1 element above --> 

<?php if($_SERVER['REQUEST_METHOD'] !== "POST"):  ?> 

<p>This program will run a frequency analysis on your text, look up each word using the Etymological Wordnet, then tally the words according to origin language family.</p> 

<form enctype="multipart/form-data" action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post"> 
<p><label for="file">Upload a file:</label> 
<input type="file" name="file" id="file"></p> 

<p>Or select a test text file to analyze: 
<select name="filename"> 
<?php 
$test_texts_dir = 'txt'; 
$test_texts = glob($test_texts_dir.'/*.txt'); 
foreach ($test_texts as $text) { 
	echo "<option value='$text'>$text</option>"; 
} ?> 
</select> 
<p><input type="checkbox" name="remove_boring" />Remove frequently used words like "the" and "a."</p> 
<button type="submit">Analyze!</button> 
</form>    

<?php endif; ?> 

<?php if($_SERVER['REQUEST_METHOD'] == "POST"):  

//error_reporting(E_ALL); 

//setup php for working with Unicode data
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
mb_http_input('UTF-8');
mb_language('uni');
mb_regex_encoding('UTF-8');
ob_start('mb_output_handler'); 

include('dblayer.php'); 

function debug_print($text) { 
	echo $text; 
	ob_flush(); 
	flush(); 
} 

// echo 'Current php version: ' . phpversion(); 

// This part adapted from https://github.com/benbalter/Frequency-Analysis 

//grab file contents
if ($_FILES["file"]["name"]!=NULL) { 
	echo "<p>Upload successful. Checking file.</p>"; 
	$temp = explode(".", $_FILES["file"]["name"]);
	if (($_FILES["file"]["type"] == "text/plain")
		&& ($_FILES["file"]["size"] < 20000)) 
	{
		if ($_FILES["file"]["error"] > 0)
		{
			echo "<p>Error: " . $_FILES["file"]["error"] . "</p>";
		}
		else
		{
			echo "<p>Upload: " . $_FILES["file"]["name"] . "</p>";
			echo "<p>Type: " . $_FILES["file"]["type"] . "</p>";
			echo "<p>Size: " . ($_FILES["file"]["size"] / 1024) . " kB</p>";
			echo "<p>Stored in: " . $_FILES["file"]["tmp_name"] . "</p>";
		}
	} else {
		echo "<p>Invalid file. Please make sure your file is a plain text file and is small enough for this program to analyze.</p>";
	}
	$test_filename=$_FILES["file"]["tmp_name"]; 
} else { 
	$test_filename=$_POST["filename"];
	echo "<p>Using test file: <a href='$test_filename'>$test_filename</a></p>"; 
	ob_flush();
	flush(); 
} 	


$content = file_get_contents($test_filename);

//if the file doesn't exist, error out
if ( !$content )
	die( 'No file to analyze. Ergo, nothing to do.' );

$content = preg_replace("/--/", " ", $content); 

$content = strip_tags($content); //make sure there aren't any HTML or TEI tags here

$content = str_word_count($content,1); //trying a different method for cleaning

//split content on words
$words = Array();

/**
 * Parses text and builds array of phrase statistics
 *
 * @param string $input source text
 * @param int $num number of words in phrase to look for
 * @return array array of phrases and counts
 */
function build_stats($input,$num) {

	//init array
	$results = array();
	
	//loop through words
	foreach ($input as $key=>$word) {
		$phrase = '';
		
		//look for every n-word pattern and tally counts in array
		for ($i=0;$i<$num;$i++) {
			if ($i!=0) $phrase .= ' ';
			$phrase .= strtolower( $input[$key+$i] );
		}
		if ( !isset( $results[$phrase] ) )
			$results[$phrase] = 1;
		else
			$results[$phrase]++;
	}
	if ($num == 1) {
		//clean boring words
		if (isset($_POST['remove_boring'])) {  
			$a = split(" ","the of and to a in that it is was i for on you he be with as by at have are this not but had his they from she which or we an there her were one do been all their has would will what if can when so my");
			foreach ($a as $banned) unset($results[$banned]);
		} 
	}
	
	//sort, clean, return
	array_multisort($results, SORT_DESC);
	unset($results[""]);
	return $results;
}

$results = build_stats($content, 1); 

$num_words = count($content); 
$unique_words = count($results); 
$basic_stats_message = "<p>This text contains $num_words words, of which $unique_words are unique.</p>"; 
debug_print($basic_stats_message); 

function lookup($word, $enm=FALSE) { 
	$word = trim($word); //strip whitespace from beginning and end of word just in case
	//connect to database
	if($enm==TRUE) { 
		//echo "Looking up middle english word $word."; 
		$query="SELECT parent_lang, parent_word FROM etym_dict WHERE word=\"$word\" AND word_lang=\"enm\""; //making this English-only for now
	} else { 
		$query="SELECT parent_lang, parent_word FROM etym_dict WHERE word=\"$word\" and word_lang=\"eng\""; //making this English-only for now
	} 
	//debug_print("<p>Query is: $query</p>"); 
	$result=dbquery($query) 
	or die("Failed to look up words in database."); 
	$parent_lang=mysqli_fetch_array($result); 
	return $parent_lang; 
} 

/* Accepts word, looks up root word
 * Example: input: goes; output: go
 */
function lookup_derivation($word) { 
	$query="SELECT parent_word FROM derivations WHERE word=\"$word\" and word_lang=\"eng\""; //making this English-only for now
	$result=dbquery($query) 
	or die("Failed to look up derivation in database."); 
	$derivation=mysqli_fetch_array($result); 
	$derivation=strtolower(trim($derivation[0])); 
	return $derivation; 
} 

//initialize list of parent languages
$parent_langs=array(); 
$grandparent_langs=array(); 
$not_in_dict=array(); 
echo "<p>Looking up $unique_words words.</p>"; 
?>

<div id="piechart" class="piechart">
</div>

<div id="piechart2" class="piechart">
</div>


<div id="wordOutput" class="box">
	<h2>Words</h2> 
	<div class="boxContent"> 
		<p class="caption">Looking up the following words in the etymology dictionary. <span class="red">Red words</span> could not be found in the dictionary (a full list follows), and <span class="blue">blue words</span> could not be found in their written forms, but were found in their root forms (given in parentheses). Green words were Middle English words not found in the dictionary. </p> 
		<p>Looking up: 

<?php 
foreach (array_keys($results) as $word) { 
	if(substr($word, -2)=="'s") { 
		$word = substr($word, 0, -2); //cut off 's from possessives
	} 
	$parent=lookup($word); 
	$parent_lang=$parent[0]; 
	$parent_word=trim($parent[1]);  
	if (!empty($parent_lang)) { //found it 
		$parent_langs[]=array($word,$parent_lang,$results[$word]); 
		debug_print("$word, "); 
		if ($parent_lang == "enm" || $parent_lang == "eng") { 
			$grandparent = lookup($parent_word, $enm=TRUE); 
			$grandparent_lang = $grandparent[0]; 
			if (!empty($grandparent_lang)) { 
				$grandparent_langs[]=array($word,$grandparent_lang,$results[$word]);
			} else { 
				$not_in_dict_2g[]=$parent_word; 
				debug_print("<span class=\"green\">$parent_word</span>, "); 
			} 	
		} else { // add non-enm words to second-generation list
			$grandparent_langs[]=array($word,$parent_lang,$results[$word]); 
		} 
	} else if(strpos($word, "-") !== FALSE) { //try hyphenated words 
			$hyphenated_word_pieces = explode("-", $word); 
			foreach ($hyphenated_word_pieces as $word_piece) { 
				$parent=lookup($word_piece); //FIXME: Don't repeat yourself
				$parent_lang=$parent[0]; 
				if (!empty($parent_lang)) { 
					$parent_langs[]=array($word_piece,$parent_lang,$results[$word]); 
					debug_print("$word_piece, "); 
				} 
			} 
	} else { // try derivations 
		$derivation=strtolower(lookup_derivation($word)); 
		$has_derivation = (strlen($derivation)>0) ? TRUE : FALSE; 
		if($has_derivation) { 
			//debug_print("Trying derivation: $derivation. "); 
			$parent=lookup($derivation); //FIXME: Don't repeat yourself
			if(!empty($parent)) { 
				$parent_lang=$parent[0]; 
				$parent_word=trim($parent[1]);  
			} else { 
				$derivation=strtolower(lookup_derivation($derivation)); //try second derivation 
				$has_derivation = (strlen($derivation)>0) ? TRUE : FALSE; 
				$parent=lookup($derivation); 
				if(!empty($parent)){ 
					$parent_lang=$parent[0]; 
					$parent_word=trim($parent[1]);  
				} 
			} 
		} 
		if(!empty($parent_lang) && $has_derivation) { 
			debug_print("<span class=\"blue\">$word ($derivation)</span>, "); 
		} else if(!empty($parent_lang)) { 
			$parent_langs[]=array($word,$parent_lang,$results[$word]); 
			$grandparent_langs[]=array($word,$parent_lang,$results[$word]);
			debug_print("<span class=\"blue\">$word</span>, "); 
		} else { 
			$not_in_dict[]=$word; 
			debug_print("<span class=\"red\">$word"); 
			if ($has_derivation) { 
				debug_print("/$derivation</span>, "); 
			} else { 
				debug_print("</span>, "); 
			} 
		} 
	} 
} 
debug_print("done.</p>"); 
//echo "Grandparent langs: "; 
//print_r($grandparent_langs); 
?> 

</div><!--end .boxContent-->
</div><!--end #wordOutput--> 

<div id="errors" class="box">
<h2>Errors</h2>
<div class="boxContent">
<p class="caption">Couldn't find these words in the etymology dictionary: </p> 
<?php foreach ($not_in_dict as $mystery_word) { 
	echo "$mystery_word, "; 
} ?> 
<p class="caption">Upon second-generation etymology, couldn't find these Middle English words in the dictionary: </p> 
<?php 
if (!empty($not_in_dict_2g)) { 	
	foreach ($not_in_dict_2g as $mystery_word) { 
		echo "$mystery_word, "; 
	} 
} ?> 
</div><!--end .boxContent--> 
</div><!--end of .box--> 


<div class="box"> 
<h2>Individual Etymologies</h2> 
<div class="boxContent"> 
<table id="wordlist"> 
	<th>Word</th>
	<th>Parent Language</th>
	<th>Frequency</th>

<?php
$lang_count=array(); 

foreach($parent_langs as $wordResult) { 
	$word=$wordResult[0];
	$pl=$wordResult[1]; 
	$freq=intval($wordResult[2]); 
	echo "<tr>
		<td>$word</td>
		<td>$pl</td>
		<td>$freq</td>
		</tr>"; 
 	if (strlen($pl)>1 && !$lang_count[$pl]) { //check to see if the parent language is already in the tally
		if ($freq>1) { 
			$lang_count[$pl]=$freq; 
		} else { 
			$lang_count[$pl]=1; 
		} 
	} else { //if it is, increment the count
		$lang_count[$pl]=$lang_count[$pl]+$freq; 
	} 	
} 
?> 

</table>
</div><!--end .boxContent --> 
</div><!--end .box --> 

<div class="box"> 
<h2>Second-Generation Individual Etymologies</h2> 
<div class="boxContent"> 
<table id="wordlist2"> 
	<th>Word</th>
	<th>(Grand)parent Language</th>
	<th>Frequency</th>

<?php
$gp_lang_count=array(); 

foreach($grandparent_langs as $wordResult) { 
	$word=$wordResult[0];
	$pl=$wordResult[1]; 
	$freq=intval($wordResult[2]); 
	echo "<tr>
		<td>$word</td>
		<td>$pl</td>
		<td>$freq</td>
		</tr>"; 
 	if (strlen($pl)>1 && !$lang_count[$pl]) { //check to see if the parent language is already in the tally
		if ($freq>1) { 
			$gp_lang_count[$pl]=$freq; 
		} else { 
			$gp_lang_count[$pl]=1; 
		} 
	} else { //if it is, increment the count
		$gp_lang_count[$pl]=$gp_lang_count[$pl]+$freq; 
	} 	
} 
?> 

</table>
</div><!--end .boxContent --> 
</div><!--end .box --> 

<div class="box"> 
<h2>First-Generation Languages</h2> 
<div class="boxContent"> 
<table> 
	<th>Code</th>
	<th>Language</th>
	<th># Words</th>
	<th>Percentage</th>

<?php 

$lang_tree['Germanic'] = array('eng','enm','ang','goh','deu','gmh','gml','nld','non','dan','odt'); 
$lang_tree['Latinate'] =  array('fra','frm','fro','xno','ita','lat','spa','por'); 
$lang_tree['Slavic'] = array('ces','heb'); 
$lang_tree['Celtic'] = array('sga','gle'); 
$lang_tree['Afroasiatic'] = array('ara','arz'); 
$lang_tree['Iranian'] = array('fas'); 
$lang_tree['Hellenic'] = array('grc'); 
$lang_tree['Other'] = array('nan'); 

function look_up_lang($lang) { 
	$query="SELECT language FROM lang_dict WHERE code=\"$lang\""; 
	//echo "<p>Query is: $query</p>"; 
	$result=dbquery($query) 
	or die("Failed to look up language code in database."); 
	$lang_full=mysqli_fetch_array($result); 
	$lang_full=$lang_full[0]; 
	return $lang_full; 
} 

foreach($lang_count as $lang => $count) { //loop through the raw languages list 
	foreach($lang_tree as $lang_family => $lang_children) { //count languages according to their language families 
		if (in_array($lang, $lang_children)) { 
			$families[$lang_family] = $families[$lang_family]+$count; 
		} 
	} 
	$percentage = round(($count/$num_words*100), 2); 
	$lang_full = look_up_lang($lang); 
	echo "<tr> 
		<td>$lang</td>
		<td>$lang_full</td> 
		<td>$count</td>
		<td>$percentage</td> 
		</tr>"; 
} 

$families['Unknown']=count($not_in_dict); 
$families_total = array_sum(array_values($families)); 
foreach($families as $family => $count) { 
	$family_percentage = round(($count / $families_total * 100), 2);  
	$families[$family] = array($count, $family_percentage); 
} 
?> 

</table>
</div><!--end .boxContent --> 
</div><!--end .box --> 


<div class="box"> 
<h2>Second-Generation Languages</h2> 
<div class="boxContent"> 
<table> 
	<th>Code</th>
	<th>Language</th>
	<th># Words</th>
	<th>Percentage</th>

<?php 

foreach($gp_lang_count as $lang => $count) { //loop through the raw languages list 
	foreach($lang_tree as $lang_family => $lang_children) { //count languages according to their language families 
		if (in_array($lang, $lang_children)) { 
			$gp_families[$lang_family] = $gp_families[$lang_family]+$count; 
		} 
	} 
	$percentage = round(($count/$num_words*100), 2); 
	$lang_full = look_up_lang($lang); 
	echo "<tr> 
		<td>$lang</td>
		<td>$lang_full</td> 
		<td>$count</td>
		<td>$percentage</td> 
		</tr>"; 
} 

$gp_families['Unknown']=count($not_in_dict)+count($not_in_dict_2g); 
$gp_families_total = array_sum(array_values($families)); 
foreach($gp_families as $family => $count) { 
	$gp_family_percentage = round(($count / $families_total * 100), 2);  
	$gp_families[$family] = array($count, $gp_family_percentage); 
} 

?> 

</table>
</div><!--end .boxContent --> 
</div><!--end .box --> 

<div class="box"> 
<h2>First-Generation Language Families</h2> 
<div class="boxContent"> 
<table> 
<th>Family</th>
<th># Words</th>
<th>Percentage</th>
<?php foreach ($families as $family => $count) { 
	$raw_count = $count[0]; 
	$perc = $count[1]; 
	echo "<tr><td>$family</td><td>$raw_count</td><td>$perc</td></tr>"; 
} ?> 
</table> 
</div><!--end .boxContent --> 
</div><!--end .box --> 

<div class="box"> 
<h2>Second-Generation Language Families</h2> 
<div class="boxContent"> 
<table> 
<th>Family</th>
<th># Words</th>
<th>Percentage</th>
<?php foreach ($gp_families as $family => $count) { 
	$raw_count = $count[0]; 
	$perc = $count[1]; 
	echo "<tr><td>$family</td><td>$raw_count</td><td>$perc</td></tr>"; 
} ?> 
</table> 
</div><!--end .boxContent --> 
</div><!--end .box --> 

<div id="log" class="box">
<h2>Logging</h2>
<div class="boxContent">
<p class="caption">Log file: </p> 

<?php // logging
$logfile = 'log.txt'; 
//echo "Families: "; 
//print_r($families); 
$log_content = join(',', array($test_filename, $families["Germanic"][1],$gp_families["Germanic"][1],$families["Latinate"][1],$gp_families["Latinate"][1],$families["Hellenic"][1],$families["Unknown"][1])) . "\n"; //only using these families for now

// Let's make sure the file exists and is writable first.
if (is_writable($logfile)) {

    if (!$handle = fopen($logfile, 'a')) {
         echo "Cannot open file ($logfile)";
         exit;
    }

    // Write $log_content to our opened file.
    if (fwrite($handle, $log_content) === FALSE) {
        echo "Cannot write to file ($logfile)";
        exit;
    }

    echo "<p>Success, wrote ($log_content) to file <a href='$logfile'>($logfile)</a></p>";

    fclose($handle);

} else {
    echo "The file $logfile is not writable";
}
?> 
</p>
</div><!--end .boxContent--> 
</div><!--end of .box--> 

<script type="text/javascript" src="https://www.google.com/jsapi"></script>
    <script type="text/javascript">
      google.load("visualization", "1", {packages:["corechart"]});
      google.setOnLoadCallback(drawChart);
      function drawChart() {
        var data = google.visualization.arrayToDataTable([
		['Family', 'Count'],
<?php foreach ($families as $family => $count) { 
	$count = $count[0]; 
	echo "['$family', $count],"; 
} ?> 
	]); 

        var options = {
          title: 'First Generation Parent Language Families',
          is3D: true,
        };

        var chart = new google.visualization.PieChart(document.getElementById('piechart'));
        chart.draw(data, options);
      }
    </script>
  
    <script type="text/javascript">
      google.load("visualization", "1", {packages:["corechart"]});
      google.setOnLoadCallback(drawChart);
      function drawChart() {
        var data = google.visualization.arrayToDataTable([
		['Family', 'Count'],
<?php foreach ($gp_families as $family => $count) { 
	$count = $count[0]; 
	echo "['$family', $count],"; 
} ?> 
	]); 

        var options = {
          title: 'Second Generation Parent Language Families',
          is3D: true,
        };

        var chart = new google.visualization.PieChart(document.getElementById('piechart2'));
        chart.draw(data, options);
      }
    </script>

<?php endif; ?> 

</div> <!-- end of #container --> 

<footer> 
<p>Created by Jonathan Reeve. Released under the GPLv3.</p> 
<p>Examine or fork the source code for this program at <a href="http://github.com/JonathanReeve/bulk-etym/">http://github.com/JonathanReeve/bulk-etym/</a>.</p>  
</footer>    
</body>    
</html>    
