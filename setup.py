from distutils.core import setup
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
      macroetym = macroetym.main:cli
  ''',
)
