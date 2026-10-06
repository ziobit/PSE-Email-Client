#!/usr/bin/env python3
"""Offline regressions for merge discovery, versions, and bundled changelogs."""

import base64
import importlib.util
import json
import os
from pathlib import Path
import re
import subprocess
import tempfile
import unittest
from unittest import mock


SCRIPT = Path(__file__).resolve().parents[1] / 'scripts/update-changelog.py'
SPEC = importlib.util.spec_from_file_location('pse_changelog', SCRIPT)
CHANGELOG = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(CHANGELOG)
REPOSITORY = 'ziobit/PSE-Email-Client'
DATE = '2026-10-06T13:30:00Z'


def php(version='2.18.1', notes='notes'):
  return "<?php\n/*\n * PSE Email (PSE), release v%s\n */\nconst PSE_VERSION = '%s';\n%s\nfunction pseBundledChangelogText(): string\n{\n  return base64_decode('%s', true) ?: '';\n}\n%s\n// preserved application code\n" % (
    version, version, CHANGELOG.START,
    base64.b64encode(notes.encode('utf-8')).decode('ascii'), CHANGELOG.END
  )


def pull(number, commit, title='Improve email reading', merged=True, base='main'):
  return {
    'number': number, 'title': title, 'merged': merged,
    'merged_at': DATE if merged else None, 'merge_commit_sha': commit,
    'base': {'ref': base, 'repo': {'full_name': REPOSITORY}}
  }


class RepositoryFixture:
  def __init__(self, path):
    self.path = path
    self.run('init', '-b', 'main')
    self.run('config', 'user.name', 'Fixture maintainer')
    self.run('config', 'user.email', 'fixture@example.test')
    self.base = self.commit('Base release', '2.17.30')

  def run(self, *args):
    return CHANGELOG.git(*args, root=self.path)

  def commit(self, subject, version=None, name='index.php', content=None):
    if version is not None:
      content = php(version)
    if content is not None:
      file = self.path / name
      file.parent.mkdir(parents=True, exist_ok=True)
      file.write_text(content, encoding='utf-8')
    self.run('add', '--', name)
    self.run('commit', '-m', subject)
    return self.run('rev-parse', 'HEAD')

  def merge(self):
    self.run('checkout', '-b', 'feature-merge')
    self.commit('Show sent recipients', '2.18.1')
    self.run('checkout', 'main')
    self.run('merge', '--no-ff', '-m', 'Merge feature branch', 'feature-merge')
    return self.run('rev-parse', 'HEAD')

  def squash(self):
    self.run('checkout', '-b', 'feature-squash')
    self.commit('Add custom cleanup dates', content='cleanup', name='cleanup.txt')
    self.commit('Add confirmation', content='confirmed', name='cleanup.txt')
    self.run('checkout', 'main')
    self.run('merge', '--squash', 'feature-squash')
    self.run('commit', '-m', 'Folder cleanup (#2)')
    return self.run('rev-parse', 'HEAD')

  def rebase(self):
    self.run('checkout', '-b', 'feature-rebase')
    self.commit('Create black cat icon', content='cat', name='cat.txt')
    self.commit('Document file association', content='windows', name='windows.txt')
    self.run('checkout', 'main')
    self.commit('Concurrent documentation', content='docs', name='docs.txt')
    prior = self.run('rev-parse', 'HEAD')
    self.run('checkout', 'feature-rebase')
    self.run('rebase', 'main')
    self.run('checkout', 'main')
    self.run('merge', '--ff-only', 'feature-rebase')
    commits = self.run('rev-list', '--reverse', prior + '..HEAD').splitlines()
    return commits


class ChangelogTests(unittest.TestCase):
  def setUp(self):
    self.temp = tempfile.TemporaryDirectory()
    self.path = Path(self.temp.name)
    self.repo = RepositoryFixture(self.path)

  def tearDown(self):
    self.temp.cleanup()

  def test_merge_squash_rebase_are_recorded_once_with_actual_metadata(self):
    merge = self.repo.merge()
    squash = self.repo.squash()
    rebased = self.repo.rebase()
    first = pull(1, merge, 'Sent recipient names')
    second = pull(2, squash, 'Folder cleanup')
    third = pull(3, rebased[-1], 'Black cat and Windows PSE files')
    metadata = {merge: [first], squash: [second]}
    for commit in rebased:
      metadata[commit] = [third, third]
    commits = CHANGELOG.pending_commits(self.repo.base, self.repo.run('rev-parse', 'HEAD'), self.path)
    self.assertIn(merge, commits)
    self.assertIn(squash, commits)
    self.assertTrue(set(rebased) <= set(commits))
    entries = CHANGELOG.collect_entries(commits, REPOSITORY, 'unused', root=self.path,
      fetch=lambda repository, commit, token: metadata.get(commit, []))
    self.assertEqual(len(entries), 4)
    text = '# Changelog\n\n## 2.18.1 (2026-10-06)\n\n- Hand-written notes.\n'
    for entry in entries:
      text = CHANGELOG.insert_entry(text, entry)
    self.assertIn('- Hand-written notes.', text)
    self.assertEqual(text.count('### Merged changes'), 1)
    for number in (1, 2, 3):
      self.assertEqual(text.count('<!-- pse-pr:%s#%s -->' % (REPOSITORY, number)), 1)
    self.assertIn('Concurrent documentation', text)
    again = text
    for entry in entries:
      again = CHANGELOG.insert_entry(again, entry)
    self.assertEqual(again, text)

  def test_unmerged_other_branch_and_old_associations_are_excluded(self):
    merge = self.repo.merge()
    squash = self.repo.squash()
    commits = [squash]
    candidates = [pull(1, merge), pull(2, squash, merged=False), pull(3, squash, base='dev'), pull(4, squash)]
    entries = CHANGELOG.collect_entries(commits, REPOSITORY, 'unused', root=self.path,
      fetch=lambda *args: candidates)
    self.assertEqual([entry['marker'] for entry in entries], ['pse-pr:%s#4' % REPOSITORY])

  def test_local_merge_without_pr_is_recorded(self):
    merge = self.repo.merge()
    entries = CHANGELOG.collect_entries([merge], REPOSITORY, 'unused', root=self.path, fetch=lambda *args: [])
    self.assertEqual(len(entries), 1)
    self.assertEqual(entries[0]['marker'], 'pse-merge:' + merge)
    self.assertIn('Merge feature branch', entries[0]['line'])

  def test_missing_squash_and_rebase_metadata_preserves_every_change(self):
    self.repo.merge()
    squash = self.repo.squash()
    rebased = self.repo.rebase()
    commits = CHANGELOG.pending_commits(self.repo.base, self.repo.run('rev-parse', 'HEAD'), self.path)
    entries = CHANGELOG.collect_entries(commits, REPOSITORY, 'unused', root=self.path, fetch=lambda *args: [])
    self.assertEqual({entry['commit'] for entry in entries}, set(commits))
    self.assertIn('pse-merge:' + squash, {entry['marker'] for entry in entries})
    self.assertTrue({'pse-merge:' + commit for commit in rebased} <= {entry['marker'] for entry in entries})
    _, version = CHANGELOG.prepare_release(php(), '2.18.1', '# Changelog\n', entries)
    self.assertEqual(version, '2.18.2')
    self.assertEqual({entry['version'] for entry in entries}, {'2.18.2'})

  def test_later_run_recovers_metadata_without_a_closed_event(self):
    self.repo.merge()
    squash = self.repo.squash()
    generic = CHANGELOG.local_merge_entry(squash, REPOSITORY, self.path)
    text = '# Changelog\n\n## 2.18.1\n\n- Existing notes.\n'
    _, version = CHANGELOG.prepare_release(php(), '2.18.1', text, [generic])
    self.assertEqual(version, '2.18.2')
    text = CHANGELOG.insert_entry(text, generic)
    metadata = pull(2, squash, 'Indexed squash metadata')
    metadata['updated_at'] = DATE
    with mock.patch.object(CHANGELOG, 'github_json', return_value=[metadata]) as api:
      repaired = CHANGELOG.repair_generic_entries(text, REPOSITORY, 'unused', self.path)
    self.assertEqual(api.call_count, 1)
    self.assertIn('pse-pr:%s#2' % REPOSITORY, repaired.split('## 2.18.1')[0])
    self.assertNotIn('pse-merge:', repaired)
    self.assertIn('Indexed squash metadata', repaired)

  def test_merged_event_repairs_delayed_pr_metadata_after_checkpoint(self):
    merge = self.repo.merge()
    generic = CHANGELOG.local_merge_entry(merge, REPOSITORY, self.path)
    text = CHANGELOG.insert_entry('# Changelog\n\n', generic)
    entries = CHANGELOG.collect_entries([], REPOSITORY, 'unused', {'pull_request': pull(11, merge)}, self.path,
      fetch=lambda *args: [])
    text = CHANGELOG.insert_entry(text, entries[0])
    self.assertIn('pse-pr:%s#11' % REPOSITORY, text)
    self.assertNotIn('pse-merge:', text)
    self.assertEqual(text.count('Commit ['), 1)

  def test_bot_commit_is_not_queried_or_recorded(self):
    self.repo.run('config', 'user.email', CHANGELOG.BOT_EMAIL)
    bot = self.repo.commit(CHANGELOG.BOT_SUBJECT, content='# Changelog\n', name='CHANGELOG.md')
    self.assertTrue(CHANGELOG.bot_commit(bot, self.path))
    entries = CHANGELOG.collect_entries([bot], REPOSITORY, 'unused', root=self.path,
      fetch=lambda *args: self.fail('Own bot commit must not query the PR API'))
    self.assertEqual(entries, [])

  def test_checkpoint_outside_main_fails(self):
    self.repo.run('checkout', '-b', 'unmerged')
    other = self.repo.commit('Other branch', content='other', name='other.txt')
    self.repo.run('checkout', 'main')
    with self.assertRaisesRegex(ValueError, 'outside main history'):
      CHANGELOG.pending_commits(other, self.repo.base, self.path)

  def test_existing_version_notes_are_preserved_and_new_versions_prepend(self):
    first = CHANGELOG.pull_entry(pull(1, 'a' * 40), REPOSITORY, '2.18.1')
    second = CHANGELOG.pull_entry(pull(2, 'b' * 40), REPOSITORY, '2.18.2')
    text = '# Changelog\n\nContext.\n\n## 2.18.1 (2026-10-06)\n\n- Manual notes.\n\n## 2.17.30 (2026-09-18)\n\n- Older notes.\n'
    text = CHANGELOG.insert_entry(text, first)
    text = CHANGELOG.insert_entry(text, second)
    self.assertLess(text.index('## 2.18.2'), text.index('## 2.18.1'))
    self.assertIn('- Manual notes.', text)
    self.assertIn('- Older notes.', text)

  def test_untrusted_titles_are_data_and_markdown_escaped(self):
    title = '<script>alert(1)</script> [x](javascript:evil)\n$(touch /tmp/never-run) `id`'
    entry = CHANGELOG.pull_entry(pull(7, 'a' * 40, title), REPOSITORY, '2.18.1')
    self.assertIn('&lt;script&gt;', entry['line'])
    self.assertIn('\\[x\\]', entry['line'])
    self.assertIn('$\\(touch /tmp/never-run\\)', entry['line'])
    self.assertNotIn('<script>', entry['line'])

  def test_same_version_merge_bumps_patch_and_both_php_declarations(self):
    entry = CHANGELOG.pull_entry(pull(1, 'a' * 40), REPOSITORY, '2.18.1')
    changed, version = CHANGELOG.prepare_release(php(), '2.18.1', '# Changelog\n', [entry])
    self.assertEqual(version, '2.18.2')
    self.assertIn("PSE_VERSION = '2.18.2'", changed)
    self.assertIn('release v2.18.2', changed)
    self.assertEqual(entry['version'], '2.18.2')

  def test_explicit_higher_version_is_preserved(self):
    entry = CHANGELOG.pull_entry(pull(1, 'a' * 40), REPOSITORY, '2.19.0')
    source = php('2.19.0')
    changed, version = CHANGELOG.prepare_release(source, '2.18.1', '# Changelog\n', [entry])
    self.assertEqual(version, '2.19.0')
    self.assertEqual(changed, source)

  def test_no_merges_or_repeat_metadata_do_not_bump_version(self):
    entry = CHANGELOG.pull_entry(pull(1, 'a' * 40), REPOSITORY, '2.18.1')
    text = CHANGELOG.insert_entry('# Changelog\n', entry)
    for entries in ([], [entry]):
      changed, version = CHANGELOG.prepare_release(php(), '2.18.1', text, entries)
      self.assertEqual(version, '2.18.1')
      self.assertEqual(changed, php())
    generic_text = '# Changelog\n<!-- pse-merge:' + entry['commit'] + ' -->\n'
    _, version = CHANGELOG.prepare_release(php(), '2.18.1', generic_text, [entry])
    self.assertEqual(version, '2.18.1')

  def test_delayed_pr_metadata_keeps_automatic_publication_release(self):
    merge = self.repo.merge()
    generic = CHANGELOG.local_merge_entry(merge, REPOSITORY, self.path)
    text = '# Changelog\n\n## 2.18.1 (2026-10-06)\n\n- Earlier notes.\n'
    bumped_php, version = CHANGELOG.prepare_release(php(), '2.18.1', text, [generic])
    self.assertEqual(version, '2.18.2')
    text = CHANGELOG.insert_entry(text, generic)
    original = text
    entry = CHANGELOG.pull_entry(pull(11, merge), REPOSITORY, '2.18.1')
    repaired_php, version = CHANGELOG.prepare_release(bumped_php, '2.18.2', text, [entry])
    self.assertEqual(version, '2.18.2')
    self.assertEqual(repaired_php, bumped_php)
    self.assertEqual(entry['version'], '2.18.2')
    text = CHANGELOG.insert_entry(text, entry)
    self.assertIn('pse-pr:%s#11' % REPOSITORY, text.split('## 2.18.1')[0])
    self.assertNotIn('pse-merge:', text)
    self.assertIn('- Earlier notes.', text.split('## 2.18.1')[1])
    # insert_entry itself is also safe when called without prepare_release.
    late_entry = CHANGELOG.pull_entry(pull(11, merge), REPOSITORY, '2.18.1')
    direct = CHANGELOG.insert_entry(original, late_entry)
    self.assertIn('pse-pr:%s#11' % REPOSITORY, direct.split('## 2.18.1')[0])

  def test_multiple_collapsed_merges_share_a_new_publication(self):
    entries = [CHANGELOG.pull_entry(pull(i, ('a' if i == 1 else 'b') * 40), REPOSITORY, '2.18.1') for i in (1, 2)]
    _, version = CHANGELOG.prepare_release(php(), '2.18.1', '# Changelog\n', entries)
    self.assertEqual(version, '2.18.2')
    self.assertEqual({entry['version'] for entry in entries}, {'2.18.2'})

  def test_bundle_round_trips_and_preserves_outside_block(self):
    text = '# Changelog\n\n## 2.18.1\n\n- Café, black cat 🐈 and Thai ไทย.\n'
    original = php()
    updated = CHANGELOG.sync_embedded_changelog(original, text, REPOSITORY)
    encoded = re.search(r"base64_decode\('([^']+)'", updated).group(1)
    self.assertEqual(base64.b64decode(encoded).decode('utf-8'), text)
    self.assertEqual(updated.split(CHANGELOG.START)[0], original.split(CHANGELOG.START)[0])
    self.assertEqual(updated.split(CHANGELOG.END)[1], original.split(CHANGELOG.END)[1])
    self.assertEqual(CHANGELOG.sync_embedded_changelog(updated, text, REPOSITORY), updated)

  def test_bundle_cap_keeps_complete_recent_release_and_full_history_link(self):
    recent = '# Changelog\n\n## 2.18.1\n\n- New changes.\n\n'
    text = recent + '## 2.18.0\n\n' + ('- Older ไทย.\n' * 50)
    bundled = CHANGELOG.bundled_text(text, REPOSITORY, limit=260)
    self.assertLessEqual(len(bundled.encode('utf-8')), 260)
    self.assertIn('## 2.18.1', bundled)
    self.assertNotIn('## 2.18.0', bundled)
    self.assertIn('/blob/main/CHANGELOG.md', bundled)

  def test_missing_or_duplicate_embed_markers_fail_closed(self):
    for source in ('<?php', php() + CHANGELOG.START):
      with self.assertRaisesRegex(ValueError, 'exactly one'):
        CHANGELOG.sync_embedded_changelog(source, '# Changelog', REPOSITORY)


if __name__ == '__main__':
  unittest.main(verbosity=2)
