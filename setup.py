import sys
from distutils.core import setup

if sys.version_info[0] < 3:
    version = str(sys.version_info[0]) + '.' + str(sys.version_info[1])
    sys.exit("""
    Sorry! Your Python version is %s, but macro-etym requires at least
    Python 3. Please upgrade your Python installation, or try using pip3
    instead of pip.""" % version)

setup(
  name = 'macroetym',
  packages = ['macroetym'], # this must be the same as the name above
  version = '0.1',
  description = 'A tool for macro-etymological textual analysis.',
  author = 'Jonathan Reeve',
  author_email = 'jon.reeve@gmail.com',
  url = 'https://github.com/JonathanReeve/macro-etym',
  download_url = 'https://github.com/JonathanReeve/macro-etym/tarball/0.1', # FIXME: make a git tag and confirm that this link works
  install_requires = ['Click', 'nltk', 'pycountry', 'pandas',
                      'matplotlib'],
  include_package_data = True,
  package_data = {'macroetym': ['etymwm-smaller.tsv']},
  keywords = ['nlp', 'text-analysis', 'etymology'],
  classifiers = [],
  entry_points='''
  [console_scripts]
  macroetym = macroetym.main:cli''',
)
