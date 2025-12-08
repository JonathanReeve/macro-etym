"""
This module handles the creation of the etymological database from Kaikki and Glottolog data.
"""
import requests
import gzip
import json
import sqlite3
import os
import tarfile
import tempfile
from pathlib import Path
from rich.progress import Progress
import logging
import newick
from pycldf import Wordlist
from pycountry import languages

def download_kaikki_data():
    """
    Downloads the Kaikki data file to the application's data directory.
    """
    url = "https://kaikki.org/dictionary/raw-wiktextract-data.jsonl.gz"
    data_dir = Path.home() / ".local" / "share" / "macroetym"
    data_dir.mkdir(parents=True, exist_ok=True)
    local_filename = data_dir / "raw-wiktextract-data.jsonl.gz"

    if local_filename.exists():
        print(f"Kaikki data already exists at: {local_filename}. Skipping download.")
        return local_filename
    
    print(f"Downloading data from {url} to {local_filename}...")
    
    try:
        with requests.get(url, stream=True) as r:
            r.raise_for_status()
            total_size = int(r.headers.get('content-length', 0))
            with open(local_filename, 'wb') as f:
                with Progress() as progress:
                    task = progress.add_task("[cyan]Downloading Kaikki data...", total=total_size)
                    for chunk in r.iter_content(chunk_size=8192):
                        f.write(chunk)
                        progress.update(task, advance=len(chunk))
        print("Kaikki data download complete.")
        return local_filename
    except requests.exceptions.RequestException as e:
        logging.error(f"Failed to download Kaikki data: {e}")
        return None

def download_glottolog_cldf():
    """
    Downloads and extracts the Glottolog-CLDF data.
    """
    url = "https://github.com/glottolog/glottolog-cldf/archive/refs/tags/v5.2.1.tar.gz"
    path = Path.home() / ".local" / "share" / "macroetym"
    path.mkdir(parents=True, exist_ok=True)
    local_filename = path / "glottolog-cldf.tar.gz"
    extracted_dir = path / "glottolog-cldf-5.2.1"

    if not extracted_dir.exists():
        print(f"Downloading Glottolog data from {url} to {local_filename}...")
        try:
            with requests.get(url, stream=True) as r:
                r.raise_for_status()
                total_size = int(r.headers.get('content-length', 0))
                with open(local_filename, 'wb') as f:
                    with Progress() as progress:
                        task = progress.add_task("[cyan]Downloading Glottolog data...", total=total_size)
                        for chunk in r.iter_content(chunk_size=8192):
                            f.write(chunk)
                            progress.update(task, advance=len(chunk))
            print("Glottolog data download complete.")
        except requests.exceptions.RequestException as e:
            logging.error(f"Failed to download Glottolog data: {e}")
            return None

        print(f"Extracting {local_filename}...")
        with tarfile.open(local_filename, "r:gz") as tar:
            tar.extractall(path=path)
        os.remove(local_filename)
        print("Extraction complete.")
    return extracted_dir

def get_glottolog_cldf_data(path):
    """Get the language data from the glottolog-cldf repository."""
    cldf_path = path / "cldf" / "cldf-metadata.json"
    return Wordlist.from_metadata(cldf_path)

def create_glottolog_table(conn):
    """Create the glottolog table in the database."""
    cursor = conn.cursor()
    cursor.execute("""
        CREATE TABLE IF NOT EXISTS glottolog (
            id TEXT PRIMARY KEY,
            name TEXT,
            family TEXT,
            latitude REAL,
            longitude REAL,
            iso639p3code TEXT,
            family_path TEXT
        )
    """)
    conn.commit()

import newick

def populate_glottolog_table(conn, data):
    """Populate the glottolog table with data."""
    cursor = conn.cursor()
    print("Populating glottolog table...")

    # 1. Read and parse the Newick trees from classification.nex
    glottolog_path = Path.home() / ".local" / "share" / "macroetym" / "glottolog-cldf-5.2.1"
    newick_file = glottolog_path / "cldf" / "classification.nex"
    
    parent_map = {}
    with open(newick_file, 'r', encoding='utf-8') as f:
        for line in f:
            if line.strip().startswith('tree'):
                try:
                    newick_string = line.split(' = ', 1)[1]
                    tree = newick.loads(newick_string)[0]
                    for node in tree.walk():
                        for child in node.descendants:
                            parent_map[child.name] = node.name
                except IndexError:
                    logging.warning(f"Could not parse tree line: {line}")
                except newick.parser.ParserError:
                    logging.warning(f"Could not parse Newick string from line: {line}")


    languages_data = {row.data['ID']: row.data for row in data.objects('LanguageTable')}

    memo = {}
    def build_family_path(lang_id):
        if lang_id in memo:
            return memo[lang_id]
        
        path_parts = []
        current_id = lang_id
        while current_id:
            name = languages_data.get(current_id, {}).get("Name", current_id)
            path_parts.append(name)
            current_id = parent_map.get(current_id)
        
        path = " > ".join(reversed(path_parts))
        memo[lang_id] = path
        return path

    with Progress() as progress:
        task = progress.add_task("[cyan]Processing Glottolog data...", total=len(languages_data))
        for lang_id, lang_info in languages_data.items():
            family_path = build_family_path(lang_id)
            family_id = lang_info.get("Family_ID")
            latitude = float(lang_info.get("Latitude")) if lang_info.get("Latitude") is not None else None
            longitude = float(lang_info.get("Longitude")) if lang_info.get("Longitude") is not None else None

            cursor.execute("""
                INSERT OR IGNORE INTO glottolog (id, name, family, latitude, longitude, iso639p3code, family_path)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            """, (lang_id, lang_info.get("Name"), family_id, latitude, longitude, lang_info.get("ISO639P3code"), family_path))
            progress.update(task, advance=1)
            
    conn.commit()
    print("Glottolog table populated.")

def create_pycountry_table(conn):
    """Create the pycountry_languages table in the database."""
    cursor = conn.cursor()
    cursor.execute("""
        CREATE TABLE IF NOT EXISTS pycountry_languages (
            alpha_2 TEXT,
            alpha_3 TEXT,
            name TEXT
        )
    """)
    conn.commit()

def populate_pycountry_table(conn):
    """Populate the pycountry_languages table with data."""
    cursor = conn.cursor()
    print("Populating pycountry_languages table...")
    with Progress() as progress:
        task = progress.add_task("[cyan]Processing pycountry data...", total=len(languages))
        for lang in languages:
            cursor.execute("""
                INSERT INTO pycountry_languages (alpha_2, alpha_3, name)
                VALUES (?, ?, ?)
            """, (getattr(lang, 'alpha_2', None), lang.alpha_3, lang.name))
            progress.update(task, advance=1)
    conn.commit()
    print("pycountry_languages table populated.")

def create_etymology_database(input_file, limit=None):
    """
    Creates the SQLite database from the Kaikki data file.
    """
    db_path = Path.home() / ".local" / "share" / "macroetym" / "etymology.sqlite"
    db_path.parent.mkdir(parents=True, exist_ok=True)
    
    if db_path.exists():
        print(f"Database already exists at {db_path}. Overwriting.")
        os.remove(db_path)

    conn = sqlite3.connect(db_path)
    c = conn.cursor()
    
    print(f"Creating database at {db_path}...")

    # Create tables
    c.execute('''
        CREATE TABLE IF NOT EXISTS words (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            word TEXT NOT NULL,
            lang TEXT NOT NULL,
            UNIQUE(word, lang)
        )
    ''')
    c.execute('''
        CREATE TABLE IF NOT EXISTS senses (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            word_id INTEGER,
            pos TEXT,
            etymology_text TEXT,
            etymology_templates_json TEXT,
            etymology_chain TEXT,
            FOREIGN KEY(word_id) REFERENCES words(id)
        )
    ''')
    print("Tables 'words' and 'senses' created.")

    # Process data and insert into database
    opener = gzip.open if str(input_file).endswith('.gz') else open
    file_size = os.path.getsize(input_file)

    with opener(input_file, 'rt', encoding='utf-8') as f:
        with Progress() as progress:
            task = progress.add_task("[cyan]Processing Kaikki data...", total=limit if limit else file_size)
            for i, line in enumerate(f):
                if limit and i >= limit:
                    break
                try:
                    data = json.loads(line)
                    word = data.get('word')
                    lang = data.get('lang')
                    
                    if word and lang:
                        # Get or create the word_id
                        c.execute("INSERT OR IGNORE INTO words (word, lang) VALUES (?, ?)", (word, lang))
                        c.execute("SELECT id FROM words WHERE word = ? AND lang = ?", (word, lang))
                        word_id_result = c.fetchone()
                        if not word_id_result:
                            continue # Should not happen with INSERT OR IGNORE, but as a safeguard
                        word_id = word_id_result[0]

                        pos = data.get('pos')
                        etymology_text = data.get('etymology_text')
                        etymology_templates = data.get('etymology_templates')
                        etymology_templates_json = json.dumps(etymology_templates) if etymology_templates else None

                        # Parse the etymology chain
                        etymology_chain = parse_etymology_templates(etymology_templates)

                        c.execute(
                            "INSERT INTO senses (word_id, pos, etymology_text, etymology_templates_json, etymology_chain) VALUES (?, ?, ?, ?, ?)",
                            (word_id, pos, etymology_text, etymology_templates_json, etymology_chain)
                        )

                except json.JSONDecodeError:
                    logging.warning(f"Skipping malformed line: {line.strip()}")
                
                if limit:
                    progress.update(task, advance=1)
                else:
                    progress.update(task, advance=len(line.encode('utf-8')))

    print("Kaikki data insertion complete. Creating index...")
    # Create index
    c.execute("CREATE INDEX IF NOT EXISTS idx_word_lang ON words (word, lang)")
    
    conn.commit()
    
    print("Etymology database initialization complete.")
    return conn

def parse_etymology_templates(templates):
    """
    Recursively parse etymology templates to extract a language chain.
    """
    if not templates:
        return ""
    
    chain = []
    
    # These are the templates that indicate a direct etymological link
    relation_templates = {"inh", "bor", "der", "mer", "cognate"}

    for template in templates:
        name = template.get("name", "")
        args = template.get("args", {})
        
        # Check if the template name indicates an etymological relation
        if any(relation_type in name for relation_type in relation_templates):
            # The language code is usually the first argument
            lang_code = args.get("1")
            if lang_code:
                chain.append(lang_code)

    return " > ".join(chain)

def init_db(limit=None):
    """
    Initializes the etymology and glottolog databases.
    """
    db_path = Path.home() / ".local" / "share" / "macroetym" / "etymology.sqlite"
    if db_path.exists():
        overwrite = input(f"Database already exists at {db_path}. Overwrite? [y/N] ")
        if overwrite.lower() != 'y':
            print("Aborting.")
            return

    # Etymology data
    kaikki_data_file = download_kaikki_data()
    if kaikki_data_file:
        conn = create_etymology_database(kaikki_data_file, limit=limit)
    else:
        print("Failed to download Kaikki data. Aborting.")
        return

    # Glottolog data
    glottolog_path = download_glottolog_cldf()
    if glottolog_path:
        glottolog_data = get_glottolog_cldf_data(glottolog_path)
        create_glottolog_table(conn)
        populate_glottolog_table(conn, glottolog_data)
    else:
        print("Failed to download Glottolog data.")
    
    # PyCountry data
    create_pycountry_table(conn)
    populate_pycountry_table(conn)

    if conn:
        conn.close()

    print("Database setup complete.")