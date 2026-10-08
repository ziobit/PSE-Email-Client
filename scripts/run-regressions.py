#!/usr/bin/env python3
"""Run the fixture suites with isolated PHP configuration and temporary storage."""

import argparse
import json
import os
from pathlib import Path
import socket
import subprocess
import sys
import tempfile
import time

ROOT = Path(__file__).resolve().parents[1]
TESTS = ROOT / 'tests'


def php_command():
  # Ignore deployment php.ini, especially IMAP (the suites define IMAP stubs).
  command = ['php', '-n', '-d', 'allow_url_fopen=0', '-d', 'allow_url_include=0',
    '-d', 'disable_functions=fsockopen,pfsockopen,stream_socket_client,mail',
    '-d', 'display_errors=stderr', '-d', 'error_reporting=-1', '-d', 'date.timezone=UTC']
  loaded = subprocess.check_output(command + ['-r', 'echo implode(",", get_loaded_extensions());'], text=True).split(',')
  # JSON is shared on some PHP 7.4 builds and built in on PHP 8; tokenizer varies.
  for extension in ('json', 'tokenizer'):
    if extension not in loaded:
      command += ['-d', 'extension=' + extension]
  subprocess.run(command + ['-r', "if (!extension_loaded('json') || !extension_loaded('tokenizer') || extension_loaded('imap') || extension_loaded('curl')) exit(1);"], check=True)
  return command


def commands(suite, php):
  if suite == 'php':
    files = subprocess.check_output(['git', 'ls-files', '--cached', '--others', '--exclude-standard', '-z', '--', '*.php'], cwd=ROOT).decode().split('\0')
    for name in sorted(set(filter(None, files))):
      yield 'Syntax: ' + name, name, php + ['-l', name]
    for path in sorted(TESTS.glob('*.php')):
      name = path.relative_to(ROOT).as_posix()
      yield name, name, php + [name]
  elif suite == 'client':
    for path in sorted(TESTS.glob('*.cjs')):
      name = path.relative_to(ROOT).as_posix()
      yield 'Syntax: ' + name, name, ['node', '--check', name]
      yield name, name, ['node', name]
  else:
    for path in sorted(TESTS.glob('*.py')):
      name = path.relative_to(ROOT).as_posix()
      yield name, name, [sys.executable, name]


def main():
  parser = argparse.ArgumentParser(description=__doc__)
  parser.add_argument('suite', choices=('php', 'client', 'python', 'all'))
  parser.add_argument('--require-offline', action='store_true', help='Fail unless only loopback exists (run inside sudo unshare --net).')
  args = parser.parse_args()
  if args.require_offline:
    try:
      interfaces = {name for _, name in socket.if_nameindex()}
    except OSError as error:
      parser.error('Unable to verify network isolation: ' + str(error))
    if interfaces != {'lo'}:
      parser.error('External network interfaces are present; run inside sudo unshare --net.')

  suites = ('php', 'client', 'python') if args.suite == 'all' else (args.suite,)
  php = php_command() if any(suite in ('php', 'client') for suite in suites) else []
  results = []
  actions = os.environ.get('GITHUB_ACTIONS') == 'true'
  for suite in suites:
    cases = list(commands(suite, php))
    if not list(TESTS.glob({'php': '*.php', 'client': '*.cjs', 'python': '*.py'}[suite])):
      raise RuntimeError('No regression suites found for ' + suite)
    for label, filename, command in cases:
      print(('::group::' if actions else '\n') + label, flush=True)
      start = time.monotonic()
      with tempfile.TemporaryDirectory(prefix='pse-regression-') as temporary:
        # Do not forward credentials, provider tokens, or deployment variables.
        environment = {name: os.environ[name] for name in ('PATH', 'LANG', 'LC_ALL', 'SYSTEMROOT', 'WINDIR', 'PATHEXT') if name in os.environ}
        environment.update(TMPDIR=temporary, TMP=temporary, TEMP=temporary,
          PYTHONDONTWRITEBYTECODE='1', PYTHONNOUSERSITE='1', GIT_CONFIG_NOSYSTEM='1',
          GIT_CONFIG_GLOBAL=os.devnull, PSE_TEST_PHP_COMMAND=json.dumps(php))
        try:
          result = subprocess.run(command, cwd=ROOT, env=environment, timeout=90)
          passed = result.returncode == 0
        except (OSError, subprocess.TimeoutExpired) as error:
          print(str(error), file=sys.stderr, flush=True)
          passed = False
      results.append((label, passed, time.monotonic() - start))
      if actions:
        print('::endgroup::', flush=True)
        if not passed:
          print('::error file=' + filename + ',title=Regression failed::' + label + ' failed. See its log group for the assertion or timeout.', flush=True)
      print(('PASS: ' if passed else 'FAIL: ') + label, flush=True)

  summary = '| Check | Result | Seconds |\n| --- | --- | --- |\n' + ''.join(
    '| %s | %s | %.2f |\n' % (label, 'PASS' if passed else 'FAIL', seconds)
    for label, passed, seconds in results)
  if actions and os.environ.get('GITHUB_STEP_SUMMARY'):
    with open(os.environ['GITHUB_STEP_SUMMARY'], 'a', encoding='utf-8') as output:
      output.write('### %s regression results\n\n%s\n' % (args.suite, summary))
  failed = sum(not passed for _, passed, _ in results)
  print('\n%d checks passed; %d failed.' % (len(results) - failed, failed), flush=True)
  return 1 if failed else 0


if __name__ == '__main__':
  sys.exit(main())
