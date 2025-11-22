"""
The Macro-Etymological Analyzer.
Author: Jonathan Reeve, jonathan@jonreeve.com
License: GPLv3

This program looks up all the words in your text, using the Etymological
Wordnet.

I made the file included here, etymwn-smaller.tsv, by running these unix
commands on the Etymological Wordnet:

First, get only those entries with the relation "rel:etymology": grep
    "rel:etymology" etymwn.tsv > etymwn-small.tsv Now we can remove the relation
    column, since it's all "rel:etymology": cat etymwn-small.tsv | cut -f1,3 >
    etymwn-smaller.tsv
"""

from collections import Counter
from string import punctuation
import codecs
import spacy
from spacy.cli.download import download as spacy_download
from spacy.tokens import Doc, Token
from importlib.resources import files
from pycountry import languages
import click
import csv
import logging
import pandas as pd

# --- SpaCy Model Management & Custom Components ---

# Define custom attributes for etymological data
if not Token.has_extension("parent_languages"):
    Token.set_extension("parent_languages", default=None)

@spacy.Language.component("etymology_lookup")
def etymology_lookup_component(doc):
    """
    SpaCy component to look up etymologies for tokens and store them in a custom attribute.
    """
    lang_3 = doc.user_data.get("lang_3")
    if not lang_3:
        return doc
    for token in doc:
        if not token.is_stop and not token.is_punct and token.is_alpha:
            word_obj = Word(token.lemma_.lower(), lang=lang_3)
            token._.parent_languages = word_obj.parent_languages
    return doc

SPACY_MODEL_MAP = {
    "eng": "en_core_web_md",
    "deu": "de_core_news_md",
    "fra": "fr_core_news_md",
    "spa": "es_core_news_md",
    "por": "pt_core_news_md",
    "ita": "it_core_news_md",
    "nld": "nl_core_news_md",
    "jpn": "ja_core_news_md",
    # Add other languages and models here
}

def load_spacy_model(lang: str):
    """
    Loads a SpaCy model, downloading it via pip if not found.
    """
    if lang not in SPACY_MODEL_MAP:
        logging.error(f"No SpaCy model defined for language code: {lang}")
        nlp = spacy.blank(lang)
        if "etymology_lookup" not in nlp.pipe_names:
            nlp.add_pipe("etymology_lookup", last=True)
        return nlp

    model_name = SPACY_MODEL_MAP[lang]
    
    try:
        nlp = spacy.load(model_name)
    except OSError:
        logging.warning(f"SpaCy model '{model_name}' not found. Downloading...")
        spacy_download(model_name)
        nlp = spacy.load(model_name)

    if "etymology_lookup" not in nlp.pipe_names:
        nlp.add_pipe("etymology_lookup", last=True)
        
    return nlp

# --- End SpaCy Model Management & Custom Components ---


# Parse the CSV file.
etymdict = {}
etymwn = files(__name__).joinpath('etymwn-smaller.tsv')
with open(etymwn, encoding='utf-8') as csvfile:
    csvreader = csv.reader(csvfile, delimiter='\t')
    for line in csvreader:
        if line[0] in etymdict:
            etymdict[line[0]].append(line[1])
        else:
            etymdict[line[0]] = [line[1]]

class LangList():
    """
    A class for language lists, that helps to count languages.
    """
    def __init__(self, langs):
        self.langs = langs

    def __repr__(self):
        return str(self.langs)

    @property
    def stats(self):
        """ Generates statistics about languages present in the list. """
        counter = Counter(self.langs)
        stats = {}
        for lang in counter.keys():
            stats[lang] = (counter[lang] / len(self.langs))*100
        return stats


class Word():
    """
    A word object, for looking up etymologies of single words.
    """
    def __init__(self, word, lang='eng', ignoreAffixes=True, ignoreCurrent=True):
        self.lang = lang
        self.word = word
        self.ignoreAffixes = ignoreAffixes
        self.ignoreCurrent = ignoreCurrent

    def __repr__(self):
        return '%s (%s)' % (self.word, self.lang)

    def __str__(self):
        return self.word

    @staticmethod
    def old_versions(language):
        """
        Returns a list of older versions of a language, such that given "eng"
        (Modern English) it will return "enm" (Middle English). This is used
        for filtering out current languages in the ignoreCurrent option of
        parents() below.
        """
        lang_map = {'eng': ['enm'],
                    'fra': ['frm', 'xno'],
                    'dut': ['dum'],
                    'gle': ['mga']}
        # TODO: add other languages here.
        return lang_map.get(language, [])

    @property
    def parents(self):
        """ A wrapper for getParents"""
        return self.getParents()

    def getParents(self, level=0):
        """
        The main etymological lookup method.

        ignoreAffixes will remove suffixes like -ly, so that the parent list
        for "universally" returns "universal (eng)" instead of "universal
        (eng), -ly (eng)."

        ignoreCurrent will ignore etymologies in the current language and
        slightly older versions of that language, so that it skips "universal
        (eng)," and goes straight to the good stuff, i.e. "universalis (lat)."
        Given a word in English, it will skip all other English and Middle
        English ancestors, but won't skip Old English.
        """
        language = self.lang

        # Finds the first-generation ancestor(s) of a word.
        try:
            raw_parent_list = etymdict[language + ": " + self.word]
        except:
            raw_parent_list = []
        parent_list = [self.split(parent) for parent in raw_parent_list]
        if self.ignoreAffixes:
            parent_list = [p for p in parent_list if p.word[0] != '-']
            parent_list = [p for p in parent_list if p.word[-1] != '-']
        if self.ignoreCurrent:
            newParents = []
            for parent in parent_list:
                if (parent.lang == language or parent.lang in self.old_versions(language)) and level < 3:
                    logging.debug('Searching deeper for word %s with lang %s' % (parent.word, parent.lang))
                    for otherParent in parent.getParents(level=level+1):
                        # Go deeper.
                        newParents.append(otherParent)
                else:
                    newParents.append(parent)
            parent_list = newParents
        return parent_list

    @property
    def parent_languages(self):
        """ Get the parent languages of a word."""
        parent_langs = []
        for parent in self.parents:
            parent_langs.append(parent.lang)
        return LangList(parent_langs)

    @property
    def grandparents(self):
        """ Get the grandparent words/languages of a word."""
        return [Word(parent.word, lang=parent.lang).parents
        for parent in self.parents]

    @property
    def grandparent_languages(self):
        """ Get the grandparent languages for a word. """
        grandparent_langs = []
        for grandparent_list in self.grandparents:
            for grandparent in grandparent_list:
                grandparent_langs.append(grandparent.lang)
        return LangList(grandparent_langs)

    @staticmethod
    def split(expression):
        """ Takes and expression in the form 'enm: not' and returns
        a Word object where word.lang is 'enm' and word.word is 'not'.
        """
        parts = expression.split(':')
        return Word(parts[1].strip(), parts[0])

class Text():
    """ A container for texts, powered by SpaCy. """
    def __init__(self, text, lang='eng', ignoreAffixes=True, ignoreCurrent=True):
        self.lang = lang
        self.ignoreAffixes = ignoreAffixes
        self.ignoreCurrent = ignoreCurrent
        
        logging.debug('Initializing text with lang %s', lang)
        if ignoreAffixes:
            logging.debug('Ignoring affixes.')
        if ignoreCurrent:
            logging.debug('Ignoring current language and its middle variants.')
            
        nlp = load_spacy_model(lang)
        
        # Manually create the Doc, set user_data, and process the pipeline
        doc = nlp.make_doc(text)
        doc.user_data["lang_3"] = self.lang
        for name, proc in nlp.pipeline:
            doc = proc(doc)
        self.doc = doc

    langDict = {'Germanic': ['eng', 'enm', 'ang', 'deu', 'dut', 'nld', 'dum',
                             'non', 'gml', 'yid', 'swe', 'rme', 'sco', 'isl',
                             'dan'],
                'Latinate': ['fra', 'frm', 'fro', 'lat', 'spa', 'xno', 'por',
                             'ita'],
                'Indo-Iranian': ['hin', 'fas'],
                'Celtic': ['gle', 'gla'],
                'Hellenic': ['grc'],
                'Semitic': ['ara', 'heb'],
                'Turkic': ['tur'],
                'Austronesian': ['tgl', 'mri', 'smo'],
                'Balto-Slavic': ['rus'],
                'Uralic': ['fin', 'hun'],
                'Japonic': ['jpn']}

    def annotate(self):
        """ Returns an annotated text in HTML format. """
        html = ""
        return html

    def showMacroEtym(self):
        for token in self.doc:
            if token._.parent_languages:
                print(token.text, token._.parent_languages)

    def getStats(self, pretty=False):
        stats_list = [token._.parent_languages.stats for token in self.doc if token._.parent_languages]
        
        stats = {}
        for item in stats_list:
            if len(item) > 0:
                for lang, perc in item.items():
                    if lang not in stats:
                        stats[lang] = perc
                    else:
                        stats[lang] += perc
        
        if not stats:
            return {}

        allPercs = sum(stats.values())
        for lang, perc in stats.items():
            stats[lang] = ( perc / allPercs ) * 100

        if pretty:
            prettyStats = {}
            for lang, perc in stats.items():
                try:
                    prettyLang = languages.get(alpha_3=lang).name
                except:
                    prettyLang = "Other Language"
                prettyStats[prettyLang] = round(perc, 2) # rename the key
            return prettyStats
        return stats

    def getFamily(self, language):
        for family, children in self.langDict.items():
            if language in children:
                return family
        return 'Other'

    def getFamilyStats(self):
        stats = self.getStats()
        families = {}
        for lang, perc in stats.items():
            fam = self.getFamily(lang)
            #print( fam, lang, perc) #debugging
            if fam in families:
                families[fam].append((lang, perc))
            else:
                families[fam] = [(lang, perc)]
        return families

    def compileFamilyStats(self, pad=True):
        families = self.getFamilyStats()
        totals = {}
        for family, langs in families.items():
            totals[family] = 0
            for lang in langs:
                totals[family] += lang[1]
        # optionally add language families not represented by the text
        if pad:
            for fam in self.langDict:
                if fam not in totals:
                    totals[fam] = 0.0
        return totals

    @property
    def stats(self):
        return self.getStats()

    def familyStats(self, pad=True):
        return self.compileFamilyStats(pad)

    @property
    def prettyStats(self):
        return self.getStats(pretty=True)

    def printPrettyStats(self, filename):
        d = {filename: self.prettyStats}
        df = pd.DataFrame(d)
        print(df)

    def printCSVStats(self, filename):
        d = {filename: self.prettyStats}
        df = pd.DataFrame(d)
        print(df.to_csv())

@click.command()
@click.argument('filenames', nargs=-1, required=True)
@click.option('--allstats', is_flag=True,
        help="Get all etymological statistics about the file(s).")
@click.option('--lang', default='eng',
        help="Specify the language of the texts. Use ISO639-3 "\
             "three-letter language code. Default is English.")
@click.option('--showfamilies', help="A comma-separated list of language "\
              "families to show, e.g. Latinate,Germanic")
@click.option('--affixes', is_flag=True, help="Don't ignore affixes. "\
              "Default is to ignore them.")
@click.option('--current', is_flag=True, help="Don't ignore current language "\
              "and its middle variants. Default is to ignore them.")
@click.option('-c', '--csv', is_flag=True, help="Print a machine-readable "
              "CSV instead of a pretty table.")
@click.option('--chart', is_flag=True, help="Make a pretty graph of the "\
              "results. For one text, a pie; for multiple, a bar.")
@click.option('--verbose', is_flag=True, help="Show debugging messages.")
def cli(filenames, allstats, lang, showfamilies, affixes,
        current, csv, chart, verbose):
    """
    Analyzes a text(s) for the etymologies of its words, and tallies the words
    by origin language, and origin language family.
    """
    single = len(filenames) == 1
    ignoreAffixes = not affixes
    ignoreCurrent = not current
    cumulativeStats = {}
    cumulativeAllStats = {}

    if verbose:
        logging.basicConfig(level=logging.DEBUG)

    for filename in filenames:
        try:
            with open(filename) as fdata:
                text = fdata.read()
        except UnicodeDecodeError as e:
            logging.error(f"Can't open file {filename} in the normal way, using utf-8.")
            logging.error(f"I'll try opening using other encodings, but you may want to Try converting it to utf-8..")
            logging.error(f"Here's the error: {e}")
            try:
                with open(filename, encoding='utf-16') as fdata:
                    text = fdata.read()
            except UnicodeDecodeError as e:
                logging.error(f"Can't open file {filename} as UTF-16, either. Try converting it to utf-8. Error: {e}")
                try:
                    with open(filename, encoding='latin1') as fdata:
                        text = fdata.read()
                except UnicodeDecodeError as e:
                    logging.error(f"Can't open file {filename} as latin1, either. Try converting it to utf-8. Error: {e}")

        t = Text(text, lang, ignoreAffixes, ignoreCurrent)

        if single and allstats and csv:
            t.printCSVStats(filename)
        elif single and allstats:
            t.printPrettyStats(filename)

        if chart and single:
            s = pd.Series(t.familyStats())
            if showfamilies:
                famlist = showfamilies.split(',')
                s = s.loc[famlist]
            ax = s.plot(kind='pie', figsize=(6,6))
            ax.set_ylabel('') # Don't write the series name "None" on the left.
            fig = ax.get_figure()
            fig.savefig('chart.png')
            print('Chart saved as chart.png.')

        cumulativeStats[filename] = t.familyStats(pad=single)
        cumulativeAllStats[filename] = t.prettyStats

    df = pd.DataFrame(cumulativeStats)
    df = df.fillna(0)

    dfAll = pd.DataFrame(cumulativeAllStats)
    dfAll = dfAll.fillna(0)

    if showfamilies:
        famlist = showfamilies.split(',')
        df = df.loc[famlist]

    if not allstats:
        if csv:
            print(df.to_csv())
        else:
            print(df)
    else:
        if csv:
            print(dfAll.to_csv())
        else:
            print(dfAll)

    if chart and not single:
        ax = df.plot(kind='bar', figsize=(6,6))
        fig = ax.get_figure()
        fig.tight_layout()
        fig.savefig('chart.png')
        print('Chart saved as chart.png.')

if __name__ == '__main__':
    cli()
