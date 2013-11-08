<?php

//error_reporting(E_ALL); 

ini_set("max_execution_time",0); 

//fix for flush 
for($i = 0; $i < 40000; $i++)
{
echo ' '; // extra spaces
} 

function debug_print($text) { 
	echo $text; 
	ob_flush(); 
	flush(); 
} 

//got this function from http://stackoverflow.com/a/2814915/584121 
function starts_with_upper($str) {
    $chr = mb_substr ($str, 0, 1, "UTF-8");
    return mb_strtolower($chr, "UTF-8") != $chr;
} 

debug_print("This script parses etymwn.tsv, the Etymological Wordnet, into a mySQL database. Don't run this script twice, otherwise it'll create duplicate entries in the database."); 

debug_print("Starting. Connecting..."); 

//setup php for working with Unicode data
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
mb_http_input('UTF-8');
mb_language('uni');
mb_regex_encoding('UTF-8');
ob_start('mb_output_handler'); 

include('dblayer.php'); 

debug_print("Connected to database."); 

debug_print("Deleting old tables and creating new ones."); 

$result=dbquery("DROP TABLE IF EXISTS etym_dict") 
	or die ("Couldn't delete existing data before reparsing."); 

$result=dbquery("DROP TABLE IF EXISTS derivations") 
	or die ("Couldn't delete existing derivations table before reparsing."); 

$result=dbquery("CREATE TABLE IF NOT EXISTS etym_dict ( 
	word VARCHAR(30),
	word_lang CHAR(3),
	parent_word VARCHAR(30),
	parent_lang CHAR(3)
	)") 
or die("Failed to create table."); 

//make the table Unicode-compatable
$result=dbquery("ALTER TABLE etym_dict CHARACTER SET utf8 COLLATE utf8_general_ci")
	or die("Failed to create table."); 

$result=dbquery("CREATE TABLE IF NOT EXISTS derivations ( 
	word VARCHAR(30),
	word_lang CHAR(3),
	parent_word VARCHAR(30),
	parent_lang CHAR(3)
	)") 
or die("Failed to create derivations table."); 

//make the table Unicode-compatable
$result=dbquery("ALTER TABLE derivations CHARACTER SET utf8 COLLATE utf8_general_ci")
	or die("Failed to make derivations table UTF-8 compatible."); 

debug_print("Created tables. "); 

debug_print("Processing stuff. "); 

function parse($line) { 
	$pattern = "/.*\trel:etymology\t.*\n/"; 
	if (preg_match_all($pattern, $line, $matches)) {
		$match = $matches[0][0]; //the matched string appears at 0:0 in the array for some reason
		//echo "matches: ".$match; 
		$match_pieces = explode("\t", $match); 
		$word_pieces = explode(": ",$match_pieces[0]); 
		$parent_pieces = explode(": ",$match_pieces[2]); 
		$word_lang = addslashes($word_pieces[0]); 
		$word = addslashes($word_pieces[1]); 
		$parent_lang = addslashes($parent_pieces[0]);
		$parent_word = addslashes($parent_pieces[1]); 
		//echo "<p>Word: $word</p>"; 
		//echo "<p>Word lang: $word_lang</p>"; 
		//echo "<p>Parent lang: $parent_lang</p>"; 
		//echo "<p>Parent Word: $parent_word</p>"; 
		
		if($word_lang !== "eng" && $word_lang !== "enm" ){ //only English words for now. 
			return; 
		}
		
		if($parent_word[0]=="-") { //don't parse parent words that begin with a hyphen, because they are probably parts of words like -tion, which aren't as useful for this purpose 
			return; 
		} 
 

		if(starts_with_upper($word)) { //skip words that begin with uppercase, since they're probably names. 
			//debug_print("Skipping word $word, since it's probably a name."); 
			return; 
		} 

		$query="INSERT INTO etym_dict(word, word_lang,parent_word, parent_lang) VALUES (\"$word\",\"$word_lang\", \"$parent_word\", \"$parent_lang\")"; 
		$result=dbquery($query)
			or die ("There was a problem inserting stuff into the etymological dictionary database."); 
	} 
	$derivations_pattern = "/.*\trel:is_derived_from\t.*\n/"; 
	if (preg_match_all($derivations_pattern, $line, $matches)) { 
		$match = $matches[0][0]; //the matched string appears at 0:0 in the array for some reason
	//	echo "matches: ".$match; 
		$match_pieces = explode("\t", $match); 
		$word_pieces = explode(": ",$match_pieces[0]); 
		$parent_pieces = explode(": ",$match_pieces[2]); 
		$word_lang = addslashes($word_pieces[0]); 
		$word = addslashes($word_pieces[1]); 
		$parent_lang = addslashes($parent_pieces[0]);
		$parent_word = addslashes($parent_pieces[1]); 
	//	echo "<p>Word: $word</p>"; 
	//	echo "<p>Word lang: $word_lang</p>"; 
	//	echo "<p>Parent lang: $parent_lang</p>"; 
	//	echo "<p>Parent Word: $parent_word</p>"; 
		// debug_print(". "); 
		$word_numwords = count(split(" ",$word)); 

		if($word_lang !== "eng" && $word_lang !== "enm") { //only English for now. 
			return; 
		} 

		if($parent_word[0]=="-") { //don't parse parent words that begin with a hyphen, because they are probably parts of words like -tion, which aren't as useful for this purpose 
			return; 
		} 

		if($word_numwords==1) { //add only single words to database
			$query="INSERT INTO derivations(word, word_lang,parent_word, parent_lang) VALUES (\"$word\",\"$word_lang\", \"$parent_word\", \"$parent_lang\")"; 
			$result=dbquery($query)
				or die ("There was a problem inserting stuff into the etymological dictionary database."); 
		} 
	} 
} 

//open the etymological wordnet for parsing
$handle = fopen("etymwn.tsv", 'r'); 
if ($handle) {
    while (($line = fgets($handle, 4096)) !== false ) {
        parse($line);
   }
 if (!feof($handle)) {
      echo "Error: unexpected fgets() fail\n";
    }
   fclose($handle);
}


$handle = fopen("etymwn-new.tsv", 'r'); 
debug_print("Now parsing custom additions to the Etymological Wordnet."); 
if ($handle) {
    while (($line = fgets($handle, 4096)) !== false ) {
        parse($line);
   }
 if (!feof($handle)) {
      echo "Error: unexpected fgets() fail\n";
    }
   fclose($handle);
}

$result=dbquery("CREATE INDEX word_index ON etym_dict (word)" )
	or die("Failed to create index on etym_dict."); 

$result=dbquery("CREATE INDEX word_index ON derivations (word)") 
	or die("Failed to create index on derivations."); 

debug_print("Closing database connection."); 

dbclose($dbc); 

?> 
