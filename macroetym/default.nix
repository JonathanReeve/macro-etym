with import <nixpkgs> {};

((python3.withPackages (ps: with ps; [
  pandas
  matplotlib
  click
  numpy
  nltk
  pycountry
])).override({ignoreCollisions=true;})).env
