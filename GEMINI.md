# Project Overview

This project, "The Macro-Etymological Analyzer," is a Python command-line tool for conducting macro-etymological analysis of text. It determines the proportion of words in a given text that originate from different language families, such as Latinate or Germanic. The tool leverages the `nltk` library for natural language processing tasks and `pandas` for data manipulation and presentation. The command-line interface is built using `click`.

The core functionality involves reading a text, tokenizing it into words, lemmatizing them, and then looking up their etymologies in a provided data file (`etymwn-smaller.tsv`). The results are then aggregated to provide statistics on the etymological origins of the words in the text.

# Roadmap 

Here are the modernization tasks I want: 

2. Handle senses or alternatives better. This will mean doing some word sense disambiguation, but only in the cases where there is more than one etymology for a given word form. Consider *bear* (the animal) and *bear* (to carry, to endure) and how they have different etymologies. There are SpaCy-compatible WSD packages like [GlossBERT](https://github.com/igormorgado/spacy-glossbert/tree/main) we might consider using, but we may also want to leverage a better etymological database. 
3. Instead of using the Etymological Wordnet (etymwn-smaller.tsv), use a more modern, better maintained etymological database which can deal with senses or ambiguous etymologies.  I'm thinking we can use Kaikki's data from [their data dump website](https://kaikki.org/dictionary/rawdata.html), and in particular  [this data https://kaikki.org/dictionary/raw-wiktextract-data.jsonl.gz](https://kaikki.org/dictionary/raw-wiktextract-data.jsonl.gz). We would want to use these glosses instead of the WordNet glosses from GlossBERT if we went this way. 
4. Be more user-friendly with data downloads. We'll need a `macroetym init` command which will download all the data. It will need to download that Kaikki data from [this location]( https://kaikki.org/dictionary/raw-wiktextract-data.jsonl.gz), and maybe load it into a database. Warn the user beforehand if it's a very large download. It will also download whatever Spacy models we need, like the en_core_web_md or equivalent.


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
    This command sets up a virtual environment in `.venv` using `uv` and installs all dependencies from `pyproject.toml`.

3.  **Download NLTK data:**
    The tool requires additional data from the `nltk` library. After activating the environment, run the following command to download it:
    ```bash
    python -c "import nltk; nltk.download('punkt'); nltk.download('stopwords'); nltk.download('averaged_perceptron_tagger'); nltk.download('wordnet')"
    ```

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
