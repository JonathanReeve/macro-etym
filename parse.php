<?php

error_reporting(E_ALL); 

echo "Starting. Connecting..."; 

include('dblayer.php'); 

echo "Connected to database."; 

//make the database
$result=dbquery("CREATE TABLE IF NOT EXISTS etym_dict ( 
	word VARCHAR(30),
	word_lang CHAR(3),
	parent_word VARCHAR(30),
	parent_lang CHAR(3)
	)") 
or die("Failed to create table."); 

function parse($line) { 
	$pattern = "/.*\trel:etymology\t.*\n/"; 
	if (preg_match_all($pattern, $line, $matches)) {
		echo "Match was found <br />";
		$match = $matches[0][0]; //the matched string appears at 0:0 in the array for some reason
		echo "matches: ".$match; 
		$match_pieces = explode("\t", $match); 
		$word_pieces = explode(": ",$match_pieces[0]); 
		$parent_pieces = explode(": ",$match_pieces[2]); 
		$word_lang = $word_pieces[0]; 
		$word = $word_pieces[1]; 
		$parent_lang = $parent_pieces[0];
		$parent_word = $parent_pieces[1]; 
		echo "<p>Word: $word</p>"; 
		echo "<p>Word lang: $word_lang</p>"; 
		echo "<p>Parent lang: $parent_lang</p>"; 
		echo "<p>Parent Word: $parent_word</p>"; 

		$query="INSERT INTO etym_dict(word, word_lang,parent_word, parent_lang) VALUES ('$word','$word_lang', '$parent_word', '$parent_lang')"; 
		$result=dbquery($query)
			or die ("There was a problem inserting stuff into the etymological dictionary database."); 
	} 
} 

//open the etymological wordnet for parsing
$handle = fopen("etymwn.tsv", 'r'); 
$i=0; 
if ($handle) {
    while (($line = fgets($handle, 4096)) !== false ) {
	echo "<p>";
        echo parse($line);
	echo "</p>";
   }
 if (!feof($handle)) {
      echo "Error: unexpected fgets() fail\n";
    }
   fclose($handle);
}

echo "Closing database connection."; 
dbclose($dbc); 
?> 
