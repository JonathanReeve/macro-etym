# Project Overview

This project, "The Macro-Etymological Analyzer," is a Python command-line tool for conducting macro-etymological analysis of text. It determines the proportion of words in a given text that originate from different language families, such as Latinate or Germanic. The tool leverages the `nltk` library for natural language processing tasks and `pandas` for data manipulation and presentation. The command-line interface is built using `click`.

The core functionality involves reading a text, tokenizing it into words, lemmatizing them, and then looking up their etymologies in a provided data file (`etymwn-smaller.tsv`). The results are then aggregated to provide statistics on the etymological origins of the words in the text.

# Building and Running

## Installation

To use this tool, you need to have Python 3 and `pip` installed.

1.  **Clone the repository:**
    ```bash
    git clone https://github.com/JonathanReeve/macro-etym
    cd macro-etym
    ```

2.  **Install the package:**
    ```bash
    pip install .
    ```

3.  **Download NLTK data:**
    The tool requires additional data from the `nltk` library. You can download this data by running the following Python command:
    ```bash
    python3 -c "import nltk; nltk.download('punkt'); nltk.download('stopwords'); nltk.download('averaged_perceptron_tagger'); nltk.download('wordnet')"
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

The project follows standard Python development practices.

*   **Dependencies:** Project dependencies are managed in `setup.py` and include `Click`, `nltk`, `pycountry`, `pandas`, and `matplotlib`.
*   **Code Style:** The code is well-documented with comments and docstrings, explaining the purpose of different parts of the code.
*   **Modularity:** The code is organized into classes like `Word` and `Text`, which encapsulate the logic for handling individual words and entire texts, respectively.
*   **Command-Line Interface:** The `click` library is used to create a user-friendly and well-documented command-line interface.
