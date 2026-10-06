#!/usr/bin/env python3
"""Record newly merged main commits and bundle notes into the single PHP file.

Only Python's standard library is used. GitHub data is parsed as JSON and never
passed to a shell. A persisted main checkpoint recovers merges after a queued
workflow is replaced or a previous run fails. Hand-written notes are preserved.
"""

import argparse
import base64
import datetime
import html
import json
import os
from pathlib import Path
import re
import subprocess
import sys
import time
import urllib.error
import urllib.parse
import urllib.request


ROOT = Path(__file__).resolve().parents[1]
STATE_PATH = Path('.github/changelog-state.json')
START = '/* PSE_EMBEDDED_CHANGELOG_START */'
END = '/* PSE_EMBEDDED_CHANGELOG_END */'
SHA_RE = re.compile(r'^[0-9a-f]{40}$')
VERSION_RE = re.compile(r"const\s+PSE_VERSION\s*=\s*['\"]([0-9]+(?:\.[0-9]+){1,3})['\"]")
BOT_SUBJECT = 'docs: record merged changes'
BOT_EMAIL = '41898282+github-actions[bot]@users.noreply.github.com'
MANAGED_PATHS = {'CHANGELOG.md', 'index.php', str(STATE_PATH)}
EMBED_LIMIT = 131072


def git(*args, root=ROOT):
  return subprocess.run(
    ['git', *args], cwd=root, check=True, text=True,
    encoding='utf-8', stdout=subprocess.PIPE, stderr=subprocess.PIPE
  ).stdout.strip()


def sha(value):
  if not isinstance(value, str) or not SHA_RE.fullmatch(value):
    raise ValueError('Invalid full commit SHA in changelog metadata.')
  return value


def markdown_text(value):
  text = ' '.join(str(value).split())
  text = ''.join(c for c in text if ord(c) >= 32 and ord(c) != 127)
  text = html.escape(text, quote=False)
  return re.sub(r'([\\`*_{}\[\]()#+.!|])', r'\\\1', text)


def utc_date(value):
  parsed = datetime.datetime.fromisoformat(str(value).replace('Z', '+00:00'))
  if parsed.tzinfo is None:
    raise ValueError('Merge timestamp must include a timezone.')
  return parsed.astimezone(datetime.timezone.utc).date().isoformat()


def github_json(repository, resource, token):
  url = 'https://api.github.com/repos/' + repository + '/' + resource
  request = urllib.request.Request(url, headers={
    'Accept': 'application/vnd.github+json',
    'Authorization': 'Bearer ' + token,
    'X-GitHub-Api-Version': '2022-11-28',
    'User-Agent': 'PSE-changelog-maintenance'
  })
  for attempt in range(3):
    try:
      with urllib.request.urlopen(request, timeout=30) as response:
        return json.load(response)
    except urllib.error.HTTPError as error:
      if error.code not in (429, 500, 502, 503, 504) or attempt == 2:
        raise RuntimeError('GitHub metadata request failed (HTTP %s).' % error.code) from None
    except (urllib.error.URLError, TimeoutError):
      if attempt == 2:
        raise RuntimeError('Could not retrieve GitHub merge metadata.') from None
    time.sleep(2 ** attempt)


def associated_pulls(repository, commit, token):
  pulls = []
  page = 1
  while True:
    batch = github_json(repository, 'commits/%s/pulls?per_page=100&page=%s' % (sha(commit), page), token)
    if not isinstance(batch, list):
      raise ValueError('GitHub returned invalid pull request metadata.')
    pulls.extend(batch)
    if len(batch) < 100:
      return pulls
    page += 1


def merged_pull(pull, repository):
  base = pull.get('base') or {}
  repo = base.get('repo') or {}
  return (
    pull.get('merged_at') is not None
    and base.get('ref') == 'main'
    and str(repo.get('full_name', '')).lower() == repository.lower()
    and isinstance(pull.get('number'), int)
    and pull['number'] > 0
    and isinstance(pull.get('merge_commit_sha'), str)
    and SHA_RE.fullmatch(pull['merge_commit_sha']) is not None
  )


def release_for_commit(commit, root=ROOT):
  match = VERSION_RE.search(git('show', sha(commit) + ':index.php', root=root))
  if match is None:
    raise ValueError('Merged index.php does not declare PSE_VERSION.')
  return match.group(1)


def version_key(version):
  if not re.fullmatch(r'[0-9]+(?:\.[0-9]+){1,3}', str(version)):
    raise ValueError('Invalid published version in changelog checkpoint.')
  parts = tuple(int(part) for part in version.split('.'))
  return parts + (0,) * (4 - len(parts))


def has_entry(markdown, entry):
  return ('<!-- ' + entry['marker'] + ' -->' in markdown
    or '<!-- pse-merge:' + entry['commit'] + ' -->' in markdown)


def recorded_entry_version(markdown, entry):
  markers = ['<!-- ' + entry['marker'] + ' -->', '<!-- pse-merge:' + entry['commit'] + ' -->']
  positions = [markdown.index(marker) for marker in markers if marker in markdown]
  if not positions:
    return None
  headings = list(re.finditer(r'^## (?:\[)?([0-9]+(?:\.[0-9]+){1,3})(?:\])?(?=[\s(]|$).*$',
    markdown[:min(positions)], re.MULTILINE))
  return headings[-1].group(1) if headings else None


def prepare_release(php, previous_version, markdown, entries):
  """A merge with no developer version bump still becomes a new app update."""
  match = VERSION_RE.search(php)
  if match is None:
    raise ValueError('index.php does not declare PSE_VERSION.')
  current = match.group(1)
  previous_key = version_key(previous_version)
  new_entries = [entry for entry in entries if not has_entry(markdown, entry)]
  version = current
  if new_entries and version_key(current) <= previous_key:
    base = max(version_key(current), previous_key)
    version = '%s.%s.%s' % (base[0], base[1], base[2] + 1)
    php = php[:match.start(1)] + version + php[match.end(1):]
    php = re.sub(r'(\* PSE Email \(PSE\), release v)' + re.escape(current) + r'\b',
      lambda heading: heading.group(1) + version, php, count=1)
  for entry in new_entries:
    entry['version'] = version
  for entry in entries:
    recorded = recorded_entry_version(markdown, entry)
    if recorded is not None:
      entry['version'] = recorded
  return php, version


def bot_commit(commit, root=ROOT):
  subject, email = git('show', '-s', '--format=%s%n%ae', sha(commit), root=root).split('\n', 1)
  paths = set(git('diff-tree', '--no-commit-id', '--name-only', '-r', commit, root=root).splitlines())
  return subject == BOT_SUBJECT and email == BOT_EMAIL and bool(paths) and paths <= MANAGED_PATHS


def pending_commits(checkpoint, head, root=ROOT):
  sha(checkpoint)
  sha(head)
  result = subprocess.run(['git', 'merge-base', '--is-ancestor', checkpoint, head], cwd=root)
  if result.returncode != 0:
    raise ValueError('Changelog checkpoint is outside main history; restore it before retrying maintenance.')
  return git('rev-list', '--first-parent', '--reverse', checkpoint + '..' + head, root=root).splitlines()


def pull_entry(pull, repository, version):
  number = pull['number']
  commit = sha(pull['merge_commit_sha'])
  date = utc_date(pull['merged_at'])
  url = 'https://github.com/' + repository
  line = '- %s: [#%s](%s/pull/%s) — %s. Commit [%s](%s/commit/%s). <!-- pse-pr:%s#%s -->\n' % (
    date, number, url, number, markdown_text(pull.get('title', 'Merged pull request')),
    commit[:7], url, commit, repository, number
  )
  return {'version': version, 'date': date, 'line': line, 'marker': 'pse-pr:%s#%s' % (repository, number), 'commit': commit}


def local_merge_entry(commit, repository, root=ROOT):
  date = utc_date(git('show', '-s', '--format=%cI', commit, root=root))
  title = markdown_text(git('show', '-s', '--format=%s', commit, root=root))
  url = 'https://github.com/' + repository + '/commit/' + commit
  line = '- %s: %s. Commit [%s](%s). <!-- pse-merge:%s -->\n' % (date, title, commit[:7], url, commit)
  return {'version': release_for_commit(commit, root), 'date': date, 'line': line, 'marker': 'pse-merge:' + commit, 'commit': commit}


def insert_entry(markdown, entry):
  if '<!-- ' + entry['marker'] + ' -->' in markdown:
    return markdown
  # A closed-merge event may arrive after push metadata was indexed. Replace
  # its generic commit entry with the now-known PR rather than duplicating it.
  if entry['marker'].startswith('pse-pr:'):
    recorded = recorded_entry_version(markdown, entry)
    if recorded is not None:
      entry = dict(entry, version=recorded)
    old_marker = '<!-- pse-merge:' + entry['commit'] + ' -->'
    markdown = ''.join(line for line in markdown.splitlines(keepends=True) if old_marker not in line)
  heading = re.search(r'^## (?:\[)?' + re.escape(entry['version']) + r'(?:\])?(?=[\s(]|$).*$', markdown, re.MULTILINE)
  if heading:
    following = re.search(r'^## ', markdown[heading.end():], re.MULTILINE)
    end = heading.end() + following.start() if following else len(markdown)
    section = markdown[heading.end():end]
    if '### Merged changes\n' not in section:
      section = section.rstrip() + '\n\n### Merged changes\n\n'
    section = section.rstrip() + '\n' + entry['line'] + '\n'
    return markdown[:heading.end()] + section + markdown[end:]
  section = '## %s (%s)\n\n### Merged changes\n\n%s\n' % (entry['version'], entry['date'], entry['line'])
  first_release = re.search(r'^## ', markdown, re.MULTILINE)
  position = first_release.start() if first_release else len(markdown.rstrip())
  before = markdown[:position].rstrip() + '\n\n'
  return before + section + markdown[position:]


def bundled_text(markdown, repository, limit=EMBED_LIMIT):
  if len(markdown.encode('utf-8')) <= limit:
    return markdown
  suffix = '\nOlder entries: https://github.com/%s/blob/main/CHANGELOG.md\n' % repository
  allowance = limit - len(suffix.encode('utf-8'))
  headings = list(re.finditer(r'^## ', markdown, re.MULTILINE))
  ends = [match.start() for match in headings[1:]] + [len(markdown)]
  fitting = [end for end in ends if len(markdown[:end].encode('utf-8')) <= allowance]
  if fitting:
    prefix = markdown[:max(fitting)].rstrip()
  else:
    prefix = markdown.encode('utf-8')[:allowance].decode('utf-8', errors='ignore')
    prefix = prefix.rsplit('\n', 1)[0]
  return prefix + suffix


def sync_embedded_changelog(php, markdown, repository):
  if php.count(START) != 1 or php.count(END) != 1:
    raise ValueError('index.php must contain exactly one embedded changelog block.')
  start = php.index(START) + len(START)
  end = php.index(END)
  if end < start:
    raise ValueError('Embedded changelog markers are out of order.')
  encoded = base64.b64encode(bundled_text(markdown, repository).encode('utf-8')).decode('ascii')
  body = "\nfunction pseBundledChangelogText(): string\n{\n  return base64_decode('%s', true) ?: '';\n}\n" % encoded
  return php[:start] + body + php[end:]


def collect_entries(commits, repository, token, event=None, root=ROOT, fetch=associated_pulls):
  pending = set(commits)
  pulls = {}
  covered = set()
  for commit in commits:
    if bot_commit(commit, root):
      continue
    for pull in fetch(repository, commit, token):
      if merged_pull(pull, repository) and pull['merge_commit_sha'] in pending:
        pulls[pull['number']] = pull
        covered.add(commit)
  # The explicit merged event is authoritative even if a prior push run has
  # advanced the checkpoint. It can also repair a generic local-merge entry.
  event_pull = (event or {}).get('pull_request') or {}
  if event_pull.get('merged') is True and merged_pull(event_pull, repository):
    commit = event_pull['merge_commit_sha']
    result = subprocess.run(['git', 'merge-base', '--is-ancestor', commit, 'HEAD'], cwd=root)
    if result.returncode != 0:
      raise ValueError('Merged PR commit is not present on checked-out main yet; retry maintenance.')
    pulls[event_pull['number']] = event_pull
  entries = [pull_entry(pull, repository, release_for_commit(pull['merge_commit_sha'], root)) for pull in pulls.values()]
  known = covered | {entry['commit'] for entry in entries}
  for commit in commits:
    if commit in known or bot_commit(commit, root):
      continue
    # GitHub associations can lag the main push. Single-parent commits may be
    # squash/rebase merges, so preserve every unmatched change rather than lose
    # its notes when a pending closed-event workflow is replaced. This also
    # records direct main pushes. A later PR lookup upgrades these markers.
    entries.append(local_merge_entry(commit, repository, root))
  return sorted(entries, key=lambda entry: (entry['date'], entry['commit']))


def repair_generic_entries(markdown, repository, token, root=ROOT):
  """Recover PR metadata even if its closed-event run was replaced in the queue."""
  commits = set(re.findall(r'<!-- pse-merge:([0-9a-f]{40}) -->', markdown))
  if not commits:
    return markdown
  oldest = min(utc_date(git('show', '-s', '--format=%cI', commit, root=root)) for commit in commits)
  page = 1
  while True:
    batch = github_json(repository, 'pulls?state=closed&base=main&sort=updated&direction=desc&per_page=100&page=%s' % page, token)
    if not isinstance(batch, list):
      raise ValueError('GitHub returned invalid closed pull request metadata.')
    for pull in batch:
      if merged_pull(pull, repository) and pull['merge_commit_sha'] in commits:
        commit = pull['merge_commit_sha']
        entry = pull_entry(pull, repository, release_for_commit(commit, root))
        markdown = insert_entry(markdown, entry)
        commits.remove(commit)
    if not commits or len(batch) < 100:
      return markdown
    # Updated order ensures PRs merged since the earliest generic change are
    # examined; a PR cannot be merged after its last update timestamp.
    updated = [utc_date(pull['updated_at']) for pull in batch if pull.get('updated_at')]
    if len(updated) == len(batch) and min(updated) < oldest:
      return markdown
    page += 1


def write_changed(path, text):
  if path.exists() and path.read_text(encoding='utf-8') == text:
    return False
  path.parent.mkdir(parents=True, exist_ok=True)
  path.write_text(text, encoding='utf-8')
  return True


def main():
  parser = argparse.ArgumentParser(description=__doc__)
  parser.add_argument('--sync-only', action='store_true', help='Bundle existing notes without fetching GitHub metadata or advancing the checkpoint.')
  args = parser.parse_args()
  repository = os.environ.get('GITHUB_REPOSITORY', 'ziobit/PSE-Email-Client')
  if not re.fullmatch(r'[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+', repository):
    raise ValueError('Invalid GitHub repository name.')
  notes_path = ROOT / 'CHANGELOG.md'
  markdown = notes_path.read_text(encoding='utf-8') if notes_path.exists() else '# PSE Email Client changelog\n\n'
  changed = []
  php_path = ROOT / 'index.php'
  php = php_path.read_text(encoding='utf-8')
  if not args.sync_only:
    token = os.environ.get('GH_TOKEN', '')
    if not token:
      raise ValueError('GH_TOKEN is required to fetch merged pull request metadata.')
    state = json.loads((ROOT / STATE_PATH).read_text(encoding='utf-8'))
    if state.get('schema') != 1:
      raise ValueError('Unsupported changelog checkpoint schema.')
    head = sha(git('rev-parse', 'HEAD'))
    commits = pending_commits(state['last_processed_commit'], head)
    event_path = os.environ.get('GITHUB_EVENT_PATH', '')
    event = json.loads(Path(event_path).read_text(encoding='utf-8')) if event_path else {}
    entries = collect_entries(commits, repository, token, event)
    php, version = prepare_release(php, state['version'], markdown, entries)
    for entry in entries:
      markdown = insert_entry(markdown, entry)
    markdown = repair_generic_entries(markdown, repository, token)
    # A manual rerun containing only our own bot commit is a no-op.
    if any(not bot_commit(commit) for commit in commits):
      state['last_processed_commit'] = head
    state['version'] = version
    state_text = json.dumps(state, indent=2) + '\n'
  php = sync_embedded_changelog(php, markdown, repository)
  # Validate everything before writing any of the three managed files.
  if write_changed(notes_path, markdown):
    changed.append('CHANGELOG.md')
  if write_changed(php_path, php):
    changed.append('index.php')
  if not args.sync_only and write_changed(ROOT / STATE_PATH, state_text):
    changed.append(str(STATE_PATH))
  print('Updated ' + ', '.join(changed) if changed else 'Changelog is already current.')


if __name__ == '__main__':
  try:
    main()
  except (ValueError, OSError, RuntimeError, subprocess.CalledProcessError) as error:
    print('Changelog maintenance failed: ' + str(error), file=sys.stderr)
    sys.exit(1)
