<?php

error_reporting(E_ALL); 
echo "Starting. Connecting..."; 

//setup php for working with Unicode data
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
mb_http_input('UTF-8');
mb_language('uni');
mb_regex_encoding('UTF-8');
ob_start('mb_output_handler'); 

include('dblayer.php'); 
echo "Connected to database."; 

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
print_r($results); 

echo "<p>Results: </p>";
//print_r(array_keys($results)); 

function lookup($word) { 
	//connect to database
	$query="SELECT parent_lang FROM etym_dict WHERE word=\"$word\""; 
	//echo "<p>Query is: $query</p>"; 
	$result=dbquery($query) 
	or die("Failed to look up words in database."); 
	$parent_lang=mysqli_fetch_row($result)[0]; 
	return $parent_lang; 
} 
//initialize list of parent languages
$parent_langs=array(); 

foreach (array_keys($results) as $word) { 
	$parent_lang=lookup($word); 
	echo "<p>Word: $word Parent lang: $parent_lang</p>"; 
	$parent_langs[$word]=array($parent_lang,$results[$word]); 
} 

print_r($parent_langs); 

?>

<table> 
	<th>Word</th>
	<th>Frequency</th>
	<th>Parent Language</th>

<?php 
foreach($parent_langs as $wordResult) { 
	print_r($wordResult); 
//	$word=$wordResult
//	$pl=$wordResult[1][0]; 
//	$freq=$wordResult[1][1]; 
//	echo "Word: $
//	echo "<tr>
//		<td>$word</td>
//		<td>$pl</td>
//		<td>$freq</td>
//		</tr>"; 
} 
		?> 
</table>

 
