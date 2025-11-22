import pytest
from click.testing import CliRunner
from macroetym.main import cli

def test_moby_dick():
    runner = CliRunner()
    result = runner.invoke(cli, ['texts/moby-dick.txt', '--lang', 'eng'])
    assert result.exit_code == 0
    assert 'Germanic' in result.output
    assert 'Latinate' in result.output

def test_temps_perdu():
    runner = CliRunner()
    result = runner.invoke(cli, ['texts/temps-perdu.txt', '--lang', 'fra'])
    assert result.exit_code == 0
    # We expect French to be mostly Latinate
    assert 'Latinate' in result.output

def test_japanese_text():
    runner = CliRunner()
    result = runner.invoke(cli, ['texts/殉情詩集.txt', '--lang', 'jpn'])
    assert result.exit_code == 0
    # For Japanese, we might not get Germanic/Latinate, but the command should succeed.
    # A simple check is that it doesn't crash and produces some output.
    assert 'Japonic' in result.output or 'Other' in result.output
