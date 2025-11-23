# Project Overview

This project, "The Macro-Etymological Analyzer," is a Python command-line tool for conducting macro-etymological analysis of text. It determines the proportion of words in a given text that originate from different language families, such as Latinate or Germanic. The tool leverages the `nltk` library for natural language processing tasks and `pandas` for data manipulation and presentation. The command-line interface is built using `click`.

The core functionality involves reading a text, tokenizing it into words, lemmatizing them, and then looking up their etymologies in a provided data file (`etymwn-smaller.tsv`). The results are then aggregated to provide statistics on the etymological origins of the words in the text.

# Roadmap

The following is the plan for the next major version of the Macro-Etymological Analyzer, which will focus on using a more modern data source and handling linguistic ambiguity.

### 1. New Data Source: Kaikki.org

The current `etymwn-smaller.tsv` will be replaced with the comprehensive etymological data from [Kaikki.org](https://kaikki.org/). This provides richer, more detailed, and more accurate information, but requires a new data processing pipeline.

### 2. Database Generation (`macroetym init`)

A new command, `macroetym init`, will be created to process the large Kaikki data file (`raw-wiktextract-data.jsonl.gz`).
- **SQLite Database:** This command will parse the JSONL file and load the data into a local SQLite database (`~/.local/share/macroetym/kaikki.sqlite`). This avoids loading the entire multi-gigabyte file into memory.
- **Schema for Ambiguity:** The database will use a two-table schema (`words` and `senses`) to correctly model words that have multiple senses with different etymologies and definitions (glosses).
- **Indexing:** A database index will be created on the `word` and `lang` columns to ensure near-instant lookups.

### 3. Word Sense Disambiguation (WSD)

To handle ambiguous etymologies, the core analysis logic will be upgraded to perform Word Sense Disambiguation.
- **Batch SQL Queries:** To analyze a text, the tool will first fetch all possible senses for all unique words in the text with a single, efficient SQL query.
- **Sentence Embeddings:** The analysis will use a `sentence-transformers` model to create vector embeddings.
- **Context-Gloss Similarity:** For each word, it will create an embedding of its context (the sentence it appears in) and compare it to the embeddings of the glosses (definitions) of its possible senses.
- **Disambiguation:** The sense whose gloss is most semantically similar to the word's context will be chosen as the correct one, and its etymology will be used for the analysis.

This upgrade will make the tool dramatically more accurate and capable of handling the complexities of natural language.


# Building and Running

## Installation

This project uses `nix` with flakes and `uv` to manage the development environment.

1.  **Clone the repository:**
    ```bash
    git clone https://github.com/JonathanReeve/macro-etym
    cd macro-etym
    ```

2.  **Activate the development environment:**
    If you have `nix` and `direnv` installed and configured, the environment will activate automatically when you enter the directory. Otherwise, you can activate it manually:
    ```bash
    nix develop
    ```
    This command sets up a virtual environment in `.venv` using `uv` and installs all dependencies from `pyproject.toml`. The first time you enter the environment, it will also download any necessary SpaCy models.

## Running the Analysis

Once installed, you can use the `macroetym` command to analyze text files.

*   **Analyze a single file:**
    ```bash
    macroetym your_text_file.txt
    ```

*   **Compare multiple files:**
    ```bash
    macroetym file1.txt file2.txt
    ```

*   **Output as CSV:**
    ```bash
    macroetym --csv your_text_file.txt
    ```

*   **Generate a chart (for a single file):**
    ```bash
    macroetym --chart your_text_file.txt
    ```

For a full list of options, you can run:
```bash
macroetym --help
```

# Development Conventions

The project follows modern Python development practices.

*   **Environment:** The development environment is managed declaratively using `nix` with flakes (see `flake.nix`).
*   **Dependencies:** Python dependencies are managed with `uv` and are defined in `pyproject.toml`. The `flake.nix` file orchestrates the setup.
*   **Code Style:** The code is well-documented with comments and docstrings, explaining the purpose of different parts of the code.
*   **Modularity:** The code is organized into classes like `Word` and `Text`, which encapsulate the logic for handling individual words and entire texts, respectively.
*   **Command-Line Interface:** The `click` library is used to create a user-friendly and well-documented command-line interface.
