#The Macro-Etymological Analyzer, v2.0.0

This is a rewrite of [The Macro-Etymological Analyzer](http://jonreeve.com/etym), a tool for etymological text analysis originally written as a web app on the LAMP stack. 

##New Features in v2.0.0

 * The web interface has been replaced with a command-line interface, making the MEA scriptable and machine-readable and writable. A web front-end to the command-line interface will be possible in a future version. 
 * It is now possible to analyze and compare multiple texts at a time. 
 * Users can filter for only those language families they care about. 

##Requirements
This Python script requires the modules csv, collections, nltk, pycountry, pandas, matplotlib, click, codecs, and logging. It runs on Python 3. 

##Usage

    python macro-etym.py moby-dick.txt pride-and-prejudice.txt
