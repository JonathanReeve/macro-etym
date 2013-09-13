#About
This php script, etym.php, accepts a text, runs a word frequency analysis on it, and then looks up the resulting wordlist in a database parsed from Gerard deMelo's [Etymological Wordnet](http://www1.icsi.berkeley.edu/~demelo/etymwn/). Ideally, the program should generate the proportions of parent languages for the text--a macro-etymology for the text. 

#Language Codes
The Etymological Wordnet uses the language codes specified in [ISO-639-3](http://www-01.sil.org/iso639%2D3/iso-639-3.tab).   

#Status
These scripts won't work yet without dblayer.php, which hasn't been added to this repository for security reasons. However, a testing site is up [here](http://jonreeve.com/dev/etym/etym.php). 

#Contents of this Directory
 * etym.php - The main program, described above. 
 * parse.php - A script to parse the Etymological Wordnet (TSV format) into a mySQL database. 
 * parse-langs.php - A script to parse ISO language codes and their corresponding English names from [the ISO 639-3 code set](http://www-01.sil.org/iso639-3/download.asp) and add them to the database. 
