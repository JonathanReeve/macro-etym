from distutils.core import setup
setup(
  name = 'macroetym',
  packages = ['macroetym'], # this must be the same as the name above
  version = '0.1',
  description = 'A tool for macro-etymological textual analysis.',
  author = 'Jonathan Reeve',
  author_email = 'jon.reeve@gmail.com',
  url = 'https://github.com/JonathanReeve/macro-etym', # use the URL to the github repo
  download_url = 'https://github.com/peterldowns/mypackage/tarball/0.1', # I'll explain this in a second
  install_requires = ['Click', 'nltk', 'pycountry', 'pandas',
                      'matplotlib'],
  include_package_data = True,
  package_data = {'macroetym': ['etymwm-smaller.tsv']}, 
  keywords = ['nlp', 'text-analysis', 'etymology'], # arbitrary keywords
  classifiers = [],
  entry_points='''
      [console_scripts]
      macroetym = macroetym.main:cli
  ''',
)
