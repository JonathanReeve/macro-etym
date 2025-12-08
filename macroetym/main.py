"""
The Macro-Etymological Analyzer.
Author: Jonathan Reeve, jonathan@jonreeve.com
License: GPLv3
"""

from collections import Counter
from string import punctuation
import codecs
import spacy
from spacy.cli.download import download as spacy_download
from spacy.tokens import Doc, Token
from importlib.resources import files
import click
import csv
import logging
import pandas as pd
import subprocess
import sqlite3
import json
import os
import gzip
from pathlib import Path
from rich.progress import Progress
import time
from . import data_download

# --- SpaCy Model Management & Custom Components ---

# Define custom attributes for etymological data
if not Token.has_extension("parent_languages"):
    Token.set_extension("parent_languages", default=None)

@spacy.Language.component("etymology_lookup")
def etymology_lookup_component(doc):
    """
    SpaCy component to look up etymologies for tokens and store them in a custom attribute.
    """
    # This component will be refactored to use the new database.
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

    nlp.max_length = 2000000

    if "etymology_lookup" not in nlp.pipe_names:
        nlp.add_pipe("etymology_lookup", last=True)
        
    return nlp

# --- End SpaCy Model Management & Custom Components ---



@click.group()
def cli():
    pass

class Analysis():
    DESIRED_SUBFAMILIES = {"Germanic", "Italic", "Indo-Aryan", "Iranian", "Hellenic", "Celtic", "Balto-Slavic"}
    MANUAL_SUBFAMILY_MAP = {
        'eng': 'Germanic',
        'deu': 'Germanic',
        'nld': 'Germanic',
        'fra': 'Italic',
        'ita': 'Italic',
        'spa': 'Italic',
        'por': 'Italic',
        'ron': 'Italic',
        'rus': 'Balto-Slavic',
        'pol': 'Balto-Slavic',
        'ukr': 'Balto-Slavic',
        'ces': 'Balto-Slavic',
        'hin': 'Indo-Aryan',
        'urd': 'Indo-Aryan',
        'ben': 'Indo-Aryan',
        'fas': 'Iranian',
        'ell': 'Hellenic',
        'gle': 'Celtic',
        'gla': 'Celtic',
        'cym': 'Celtic',
    }

    def __init__(self, text, lang, conn):
        self.text = text
        self.lang = lang
        self.conn = conn
        self.c = self.conn.cursor()
        self.language_counts = Counter()
        self.family_counts = Counter()
        self.glottolog_cache = {}
        self.iso_to_glottocodes_list = {}
        self.pycountry_cache = {}
        self._load_glottolog_cache()
        self._load_pycountry_cache()
        self._analyze()

    def _load_glottolog_cache(self):
        self.c.execute("SELECT id, name, family, iso639p3code, family_path FROM glottolog")
        for row in self.c.fetchall():
            glottocode_id = row[0]
            name = row[1]
            family_id = row[2]
            iso639p3code = row[3]
            family_path = row[4]

            self.glottolog_cache[glottocode_id] = {"name": name, "family_id": family_id, "iso639p3code": iso639p3code, "family_path": family_path}

            if iso639p3code:
                if iso639p3code not in self.iso_to_glottocodes_list:
                    self.iso_to_glottocodes_list[iso639p3code] = []
                self.iso_to_glottocodes_list[iso639p3code].append(glottocode_id)

    def _load_pycountry_cache(self):
        self.c.execute("SELECT alpha_2, alpha_3, name FROM pycountry_languages")
        for row in self.c.fetchall():
            if row[0]:
                self.pycountry_cache[row[0]] = {"alpha_3": row[1], "name": row[2]}
            if row[1]:
                self.pycountry_cache[row[1]] = {"alpha_2": row[0], "name": row[2]}

    def _get_language_name(self, iso_code):
        if iso_code in self.pycountry_cache:
            return self.pycountry_cache[iso_code]["name"]
        return iso_code # fallback

    def _analyze(self):
        nlp = load_spacy_model(self.lang)
        doc = nlp(self.text)
        
        full_lang_name = self._get_language_name(self.lang)

        # Separate words into lowercase and proper nouns
        lowercase_words = set()
        proper_nouns = set()
        for token in doc:
            if not token.is_stop and not token.is_punct and token.is_alpha:
                if token.pos_ == 'PROPN':
                    proper_nouns.add(token.text)
                else:
                    lowercase_words.add(token.lemma_.lower())

        etymologies = {}
        batch_size = 500
        
        start_time = time.time()
        
        # Batch query for lowercase words
        lemmas_list = list(lowercase_words)
        for i in range(0, len(lemmas_list), batch_size):
            batch = lemmas_list[i:i + batch_size]
            placeholders = ','.join('?' for _ in batch)
            query = f"""
                SELECT w.word, s.etymology_chain
                FROM words w
                JOIN senses s ON w.id = s.word_id
                WHERE w.word IN ({placeholders})
            """
            self.c.execute(query, batch)
            for word, etymology_chain in self.c.fetchall():
                if word not in etymologies:
                    etymologies[word] = []
                if etymology_chain:
                    etymologies[word].append(etymology_chain)

        # Batch query for proper nouns
        proper_nouns_list = list(proper_nouns)
        for i in range(0, len(proper_nouns_list), batch_size):
            batch = proper_nouns_list[i:i + batch_size]
            placeholders = ','.join('?' for _ in batch)
            query = f"""
                SELECT w.word, s.etymology_chain
                FROM words w
                JOIN senses s ON w.id = s.word_id
                WHERE w.word IN ({placeholders})
            """
            self.c.execute(query, batch)
            for word, etymology_chain in self.c.fetchall():
                if word not in etymologies:
                    etymologies[word] = []
                if etymologies[word]:
                    etymologies[word].append(etymology_chain)
        
        end_time = time.time()
        
        logging.info(f"Queried {len(lowercase_words) + len(proper_nouns)} unique words in {end_time - start_time:.2f} seconds.")

        for token in doc:
            if not token.is_stop and not token.is_punct and token.is_alpha:
                word_to_lookup = token.text if token.pos_ == 'PROPN' else token.lemma_.lower()
                if word_to_lookup in etymologies:
                    for etymology_chain in etymologies[word_to_lookup]:
                        langs = etymology_chain.split(' > ')
                        self.language_counts.update(langs)
                elif token.pos_ != 'PROPN':
                    logging.debug(f"Word '{word_to_lookup}' not found in database for language '{full_lang_name}'.")
        
        for lang, count in self.language_counts.items():
            family = self._get_family(lang)
            if family:
                self.family_counts[family] += count

    def _get_family(self, lang_code):
        # Handle proto-languages
        if lang_code.endswith('-pro'):
            return "Proto-Language"

        # 1. Try manual mapping first
        if lang_code in self.MANUAL_SUBFAMILY_MAP:
            return self.MANUAL_SUBFAMILY_MAP[lang_code]
        
        # 2. Resolve lang_code to ISO 639-3
        resolved_iso639_3_code = None
        if lang_code in self.pycountry_cache: # Could be alpha_2 or alpha_3
            if "alpha_3" in self.pycountry_cache[lang_code]:
                resolved_iso639_3_code = self.pycountry_cache[lang_code]["alpha_3"]
            else: # It's an alpha_3 code already
                resolved_iso639_3_code = lang_code
        elif len(lang_code) == 3: # Assume it's an ISO 639-3 code directly
            resolved_iso639_3_code = lang_code
        
        if resolved_iso639_3_code in self.MANUAL_SUBFAMILY_MAP:
            return self.MANUAL_SUBFAMILY_MAP[resolved_iso639_3_code]

        # 3. Try to get Glottocode and then family path
        canonical_glottocode = None
        if resolved_iso639_3_code and resolved_iso639_3_code in self.iso_to_glottocodes_list:
            canonical_glottocode = self.iso_to_glottocodes_list[resolved_iso639_3_code][0]

        if not canonical_glottocode and lang_code in self.glottolog_cache:
            canonical_glottocode = lang_code

        if canonical_glottocode and canonical_glottocode in self.glottolog_cache:
            family_path_str = self.glottolog_cache[canonical_glottocode].get("family_path")
            if family_path_str:
                family_path = family_path_str.split(' > ')
                for family in reversed(family_path):
                    if family in self.DESIRED_SUBFAMILIES:
                        return family
                return family_path[0]

        logging.debug(f"Language code '{lang_code}' could not be resolved to a family.")
        return "Unclassified"

    def get_family_stats(self):
        total = sum(self.family_counts.values())
        if total == 0:
            return {}
        
        stats = {}
        for family, count in self.family_counts.items():
            stats[family] = (count / total) * 100
        return stats

@cli.command()
@click.argument('filenames', nargs=-1, required=True)
@click.option('--allstats', is_flag=True,
        help="Get all etymological statistics about the file(s).")
@click.option('--lang', default='eng',
        help="Specify the language of the texts. Use ISO639-3 "
             "three-letter language code. Default is English.")
@click.option('--showfamilies', help="A comma-separated list of language "
              "families to show, e.g. Latinate,Germanic")
@click.option('--affixes', is_flag=True, help="Don't ignore affixes. "
              "Default is to ignore them.")
@click.option('--current', is_flag=True, help="Don't ignore current language "
              "and its middle variants. Default is to ignore them.")
@click.option('-c', '--csv', is_flag=True, help="Print a machine-readable "
              "CSV instead of a pretty table.")
@click.option('--chart', is_flag=True, help="Make a pretty graph of the "
              "results. For one text, a pie; for multiple, a bar.")
@click.option('--debug', default='warning',
              type=click.Choice(['debug', 'info', 'warning', 'error', 'critical']),
              help="Set the logging level.")
@click.option('--verbose', is_flag=True, help="Enable verbose output (alias for --debug=debug).", hidden=True)
def analyze(filenames, allstats, lang, showfamilies, affixes,
        current, csv, chart, debug, verbose):
    """
    Analyzes a text(s) for the etymologies of its words, and tallies the words
    by origin language, and origin language family.
    """
    if verbose:
        loglevel = "debug"
    else:
        loglevel = debug
    
    logging.basicConfig(level=getattr(logging, loglevel.upper()))

    db_path = Path.home() / ".local" / "share" / "macroetym" / "etymology.sqlite"
    if not db_path.exists():
        print(f"Database not found at {db_path}.")
        print("Please run `macroetym init` to create the database.")
        return

    # Load the on-disk database into an in-memory database for speed.
    disk_conn = sqlite3.connect(db_path)
    mem_conn = sqlite3.connect(':memory:')
    disk_conn.backup(mem_conn)
    disk_conn.close()

    cumulative_stats = {}

    for filename in filenames:
        try:
            with open(filename) as fdata:
                text = fdata.read()
        except UnicodeDecodeError:
            logging.warning(f"Can't open file {filename} as UTF-8. Trying other encodings...")
            try:
                with open(filename, encoding='utf-16') as fdata:
                    text = fdata.read()
            except UnicodeDecodeError:
                logging.warning(f"Can't open file {filename} as UTF-16. Trying latin1...")
                try:
                    with open(filename, encoding='latin1') as fdata:
                        text = fdata.read()
                except UnicodeDecodeError:
                    logging.error(f"Can't open file {filename} with any of the attempted encodings. Skipping.")
                    continue
        
        analysis = Analysis(text, lang, mem_conn)
        cumulative_stats[filename] = analysis.get_family_stats()

    mem_conn.close()

    df = pd.DataFrame(cumulative_stats)
    df = df.fillna(0)

    if showfamilies:
        famlist = showfamilies.split(',')
        df = df.loc[famlist]

    if csv:
        print(df.to_csv())
    else:
        print(df)

    if chart:
        if len(filenames) == 1:
            ax = df.plot(kind='pie', y=filenames[0], figsize=(6,6))
            ax.set_ylabel('')
        else:
            ax = df.plot(kind='bar', figsize=(6,6))
        
        fig = ax.get_figure()
        fig.tight_layout()
        fig.savefig('chart.png')
        print('Chart saved as chart.png.')

import subprocess


@cli.command()
@click.option('--limit', default=None, type=int, help='Number of lines to process for testing.')
def init(limit):
    """
    Initialize the etymological database from Kaikki data.
    This will download a large file (several GB).
    """
    data_download.init_db(limit=limit)

@cli.command()
def web():
    """
    Launch the web interface.
    """
    print("Launching web interface...")
    with open('web_out.log', 'wb') as out_log, open('web_err.log', 'wb') as err_log:
        try:
            subprocess.run(["streamlit", "run", "macroetym/web_ui.py"], stdout=out_log, stderr=err_log)
        except FileNotFoundError:
            print("Error: `streamlit` command not found.")
            print("Please make sure Streamlit is installed and in your PATH.")


if __name__ == '__main__':
    cli()