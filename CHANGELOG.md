# Changelog

All notable changes to this project will be documented in this file.

## [0.4.0] - 2025-12-04

### Changed
- **New Data Source**: The etymological data source has been switched from the limited `etymwn-smaller.tsv` to the comprehensive Kaikki.org dataset. Language classification now uses data from Glottolog, replacing the `pycountry` library.
- **Database Backend**: A new `init` command (`macroetym init`) has been added to download and process the Kaikki and Glottolog data into a local SQLite database (`~/.local/share/macroetym/etymology.sqlite`).
- **Hierarchical Family Analysis**: The `analyze` command now provides more granular language family classifications (e.g., "Germanic," "Italic") by using the full Glottolog family tree.
- **Performance Optimizations**: The `analyze` command has been significantly sped up through the use of an in-memory database cache and batch querying for etymological lookups.
- **Improved Logging**: The `--verbose` flag has been replaced with a more flexible `--debug` option, allowing for finer control over logging levels (e.g., `info`, `debug`).

## [0.3.0] - 2025-11-22

### Added
- **Web Interface**: A new `web` command (`macroetym web`) launches a Streamlit-based web interface.
- **Interactive Analysis**: The web UI allows users to upload text files or choose from pre-loaded texts for analysis.
- **Visualizations**: The interface displays a Plotly pie chart of language family distribution and an annotated version of the text showing the etymological history of each word.

## [0.2.0] - 2025-11-20

### Changed
- **Refactored from NLTK to SpaCy**: The entire NLP pipeline has been migrated from the Natural Language Toolkit (NLTK) to SpaCy for improved performance, modern architecture, and easier dependency management.
- **Dynamic Model Management**: The tool now automatically downloads compatible SpaCy models on-the-fly, depending on the language being analyzed. Models are stored in a local user directory (`~/.local/share/macroetym/models`).
- **Custom SpaCy Component**: A custom SpaCy component was implemented to handle etymological lookups directly within the SpaCy pipeline, making the analysis more efficient.
- **Development Environment**: The development environment, managed by `nix` and `uv`, has been updated to support these changes, including the addition of `pip` to the virtual environment to allow SpaCy's downloader to function correctly.

## [0.1.0] - 2022-01-01

### Added
- **Initial Python CLI Tool**: The project was first created as a Python command-line tool.
- **NLTK-based Analysis**: Used the NLTK library for core NLP tasks like tokenization, lemmatization, and stopword removal.
- **Core Functionality**: Provided macro-etymological analysis by calculating the proportion of words from different language families (e.g., Latinate, Germanic).
- **Basic Dependency Management**: Used standard Python packaging with `setuptools` and `pip`.

## [Pre-history] - 2010

- The "Macro-Etymological Analyzer" first existed as a web-based tool built on a classic LAMP (Linux, Apache, MySQL, PHP) stack. It performed a similar analysis but was delivered through a web interface. This version is now defunct and has been superseded by the Python command-line tool.
