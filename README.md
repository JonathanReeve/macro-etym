# The Macro-Etymological Analyzer, v2.0.0

This is a rewrite of [The Macro-Etymological Analyzer](http://jonreeve.com/etym), a tool for etymological text analysis originally written as a web app on the LAMP stack.

## New Features in v2.0.0

 * The web interface has been replaced with a command-line interface, making the MEA scriptable and machine-readable and writable. A web front-end to the command-line interface will be possible in a future version.
 * It is now possible to analyze and compare multiple texts at a time.
 * Users can filter for only those language families they care about.

## Installation
Grab a copy of the program here, and install it with pip:

git clone https://github.com/JonathanReeve/macro-etym
cd macro-etym
pip install .

If you experience errors, you could try installing with `pip3` instead.

## Usage

    macroetym moby-dick.txt pride-and-prejudice.txt
