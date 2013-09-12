<!DOCTYPE html> 
<html>
<head> 
<style type="text/css"> 

body { 
 background-color: #181C52;   
} 

table { 
 display: inline-block; 
 vertical-align: top; 
 margin: 0px 50px; 
} 
table, th, td { 
 border-collapse: collapse; 
 border: 1px solid darkblue; 
 padding: 3px; 
} 

th { 
 background-color: #181C52; 
 color: #CDD3FF; 
}  

tr:nth-child(even) { 
 background-color: #CDD3FF;  
} 

.box { 
 margin: 50px; 
 border: 1px dashed #181F52; 
 padding: 10px; 
} 

#container { 
 background-color: #fff; 
 width: 70%;  
 margin: 0 auto; 
 padding: 50px; 
} 


</style> 
</head>
<body> 
<div id="container"> 

<?php

//error_reporting(E_ALL); 

//setup php for working with Unicode data
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
mb_http_input('UTF-8');
mb_language('uni');
mb_regex_encoding('UTF-8');
ob_start('mb_output_handler'); 

include('dblayer.php'); 

echo 'Current php version: ' . phpversion(); 

// This part adapted from https://github.com/benbalter/Frequency-Analysis 

//grab file contents
$content = file_get_contents( 'test.txt' );

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
		//$a = split(" ","the of and to a in that it is was i for on you he be with as by at have are this not but had his they from she which or we an there her were one do been all their has would will what if can when so my");
		//foreach ($a as $banned) unset($results[$banned]);
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
	$query="SELECT parent_lang FROM etym_dict WHERE word=\"$word\""; 
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
	<th>Language</th>
	<th># Words</th>
	<th>Percentage</th>

<?php 
foreach($lang_count as $lang => $count) { 
	$percentage = round(($count/$num_words*100), 2); 
	echo "<tr> 
		<td>$lang</td>
		<td>$count</td>
		<td>$percentage</td> 
		</tr>"; 
} 
?> 

</table>


<script type="text/javascript" src="https://www.google.com/jsapi"></script>
    <script type="text/javascript">
      google.load("visualization", "1", {packages:["corechart"]});
      google.setOnLoadCallback(drawChart);
      function drawChart() {
        var data = google.visualization.arrayToDataTable([
		['Language', 'Percentage'],
<?php foreach($lang_count as $lang => $count) { 
	$percentage = round(($count/$num_words*100), 2); 
	echo "['$lang', $percentage],"; }  ?> 
        ]);

        var options = {
          title: 'First Generation Word Parent Languages'
        };

        var chart = new google.visualization.PieChart(document.getElementById('piechart'));
        chart.draw(data, options);
      }
    </script>
  
    <div id="piechart" style="width: 500px; height: 500px;"></div>

</div> <!-- end of #container --> 
</body>    
</html>    
