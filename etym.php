<!DOCTYPE html> 
<html lang="en">
<head> 

<link rel="stylesheet" href="style.css"> 

</head>
<body> 
<div id="container"> 


<?php 
$test_texts_dir = 'txt'; 
$test_texts = glob($test_texts_dir.'/*.txt'); 

if($_SERVER['REQUEST_METHOD'] !== "POST"):  ?> 

<p>This script will run a frequency analysis on the text, then look up each word in the word frequency list in the Etymological Wordnet. The program is super inefficient at the moment, and so expect to wait a good minute or so before the page loads. The test texts marked "toobig" are too big to load.</p> 

<form action="" method="post"> 
Text file to analyze: 
<select name="filename"> 
<?php foreach ($test_texts as $text) { 
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


// echo 'Current php version: ' . phpversion(); 

// This part adapted from https://github.com/benbalter/Frequency-Analysis 

//grab file contents

$test_filename=$_POST["filename"]; 

echo "<p>Analyzing file: <a href='$test_filename'>$test_filename</a></p>"; 
$content = file_get_contents($test_filename);

//if the file doesn't exist, error out
if ( !$content )
	die( 'Please place your source text in "input.txt" in the same directory as this file' );

//strip out bad characters
$content = preg_replace( "/(,|\"|\.|\?|:|!|;| - )/", " ", $content );
$content = preg_replace( "/\n/", " ", $content );
$content = preg_replace( "/\s\s+/", " ", $content );

//split content on words
$content = split(" ",$content);
$words = Array();
$num_words = count($content); 

/**
 * Parses text and builds array of phrase statistics
 *
 * @param string $input source text
 * @param int $num number of words in phrase to look for
 * @rerturn array array of phrases and counts
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
//print_r($results); 

//echo "<p>Results: </p>";
//print_r(array_keys($results)); 

function lookup($word) { 
	//connect to database
	$query="SELECT parent_lang FROM etym_dict WHERE word=\"$word\" and word_lang=\"eng\""; //making this English-only for now
	//echo "<p>Query is: $query</p>"; 
	$result=dbquery($query) 
	or die("Failed to look up words in database."); 
	$parent_lang=mysqli_fetch_array($result); 
	$parent_lang=$parent_lang[0]; 
	return $parent_lang; 
} 
//initialize list of parent languages
$parent_langs=array(); 
$not_in_dict=array(); 

foreach (array_keys($results) as $word) { 
	$parent_lang=lookup($word); 
	if (!empty($parent_lang)) {  
	// echo "<p>Word: $word Parent lang: $parent_lang</p>"; 
	$parent_langs[]=array($word,$parent_lang,$results[$word]); 
	} else { 
		$last_letter=substr($word, -1); 
		$last_two_letters=substr($word, -2); 
		if(strlen($word)>2 && $last_letter=="s") { //try version without -s at the end for those words that are bigger than two letters
			$word = substr($word, 0, -1); 
			$parent_lang=lookup($word); //using no -s version 
		} 
		if(!empty($parent_lang)) { 
			$parent_langs[]=array($word,$parent_lang,$results[$word]); 
		} else { 
			$not_in_dict[]=$word; 
		} 
	} 
} 
?> 

<p>Total words: <?php echo $num_words ?> </p>

<div id="piechart" style="width: 500px; height: 500px;"></div>


<div class="box">
<p>Couldn't find these words in the etymology dictionary: </p> 
<?php foreach ($not_in_dict as $mystery_word) { 
		echo "$mystery_word, "; 
} ?> 
</p>
</div>


<table> 
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

<table> 
	<th>Code</th>
	<th>Language</th>
	<th># Words</th>
	<th>Percentage</th>

<?php 

$lang_tree['Germanic'] = array('eng','enm','ang','goh','deu','gmh','gml','nld','non','dan','odt'); 
$lang_tree['Latinate'] =  array('fra','frm','fro','xno','ita','lat'); 
$lang_tree['Slavic'] = array('ces'); 
$lang_tree['Celtic'] = array('sga'); 
$lang_tree['Afroasiatic'] = array('ara'); 
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
?> 

</table>

<table> 
<th>Family</th>
<th># Words</th>
<?php foreach ($families as $family => $count) { 
	echo "<tr><td>$family</td><td>$count</td>"; 
} ?> 

</table> 

<script type="text/javascript" src="https://www.google.com/jsapi"></script>
    <script type="text/javascript">
      google.load("visualization", "1", {packages:["corechart"]});
      google.setOnLoadCallback(drawChart);
      function drawChart() {
        var data = google.visualization.arrayToDataTable([
		['Family', 'Count'],
<?php foreach ($families as $family => $count) { 
	echo "['$family', $count],"; 
} ?> 
	]); 

        var options = {
          title: 'First Generation Parent Language Families'
          is3D: true,
        };

        var chart = new google.visualization.PieChart(document.getElementById('piechart'));
        chart.draw(data, options);
      }
    </script>
  

<?php endif; ?> 

</div> <!-- end of #container --> 
</body>    
</html>    
