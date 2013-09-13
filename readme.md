#About
This php script, etym.php, accepts a text, runs a word frequency analysis on it, and then looks up the resulting wordlist in a database parsed from Gerard deMelo's [Etymological Wordnet](http://www1.icsi.berkeley.edu/~demelo/etymwn/). Ideally, the program should generate the proportions of parent languages for the text--a macro-etymology for the text. 

#Status
These scripts won't work yet without dblayer.php, which hasn't been added to this repository for security reasons. However, a testing site is up [here](http://jonreeve.com/dev/etym/etym.php). 

#Contents of this Directory
 * etym.php - The main program, described above. 
 * parse.php - A script to parse the Etymological Wordnet (TSV format) into a mySQL database. 
 * parse-langs.php - A script to parse a CSV file containing ISO language codes and their corresponding English names. 
