# The Macro-Etymological Analyzer, v2.0.0

This is a rewrite of [The Macro-Etymological Analyzer](http://jonreeve.com/etym), a tool for etymological text analysis originally written as a web app on the LAMP stack.

## New Features in v2.0.0

 * The web interface has been replaced with a command-line interface, making the MEA scriptable and machine-readable and writable. A web front-end to the command-line interface will be possible in a future version.
 * It is now possible to analyze and compare multiple texts at a time.
 * Users can filter for only those language families they care about.

## Installation
You can install this program with git and pip: 

    git clone https://github.com/JonathanReeve/macro-etym
    cd macro-etym
    pip install .

If you experience errors, you could try installing with `pip3` instead:

    pip3 install .

## Usage

To compare the macro-etymologies of _Moby Dick_ and _Pride and Prejudice_, first download the texts to your current working directory, then run: 

    macroetym moby-dick.txt pride-and-prejudice.txt

To see that data represented in a chart (experimental), try appending `--chart`. Although you might be better off outputting it as a CSV (with `--csv`) and then making your own chart using spreadsheet software. 

To see a list of options, run:

    macroetym --help

That should show you this screen: 

```
Usage: macroetym [OPTIONS] FILENAMES...

  Analyzes a text(s) for the etymologies of its words, and tallies the
  words by origin language, and origin language family.

Options:
  --allstats           Get all etymological statistics about the file(s).
  --lang TEXT          Specify the language of the texts. Use ISO639-3 three-
                       letter language code. Default is English.
  --showfamilies TEXT  A comma-separated list of language families to show,
                       e.g. Latinate,Germanic
  --affixes            Don't ignore affixes. Default is to ignore them.
  --current            Don't ignore current language and its middle variants.
                       Default is to ignore them.
  -c, --csv            Print a machine-readable CSV instead of a pretty
                       table.
  --chart              Make a pretty graph of the results. For one text, a
                       pie; for multiple, a bar.
  --verbose            Show debugging messages.
  --help               Show this message and exit.
```
