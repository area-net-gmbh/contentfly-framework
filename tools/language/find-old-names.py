#!/usr/bin/env python3
"""Find names that no longer exist since epic 014 (code base in English).

Usage, from the repository root:

    python3 tools/language/find-old-names.py                # the whole tree
    python3 tools/language/find-old-names.py FILE...        # selected files

The list of old names is not maintained by hand. It is derived from git on every run: every
class, method, constant, config key, container key, console command and environment variable
that existed before epic 014 (BASE) and no longer exists at HEAD, plus the German string
literals of lib/, custom/, bin/ and index.php that are gone (searched in Markdown files only;
in code, German messages are the language guard's job). EXTRA adds what the derivation
cannot see (directory names, a derivation context, prose compounds).

Many old names are ordinary German words (`liste`, `daten`, `lesen`). German prose in
an_project/docs is allowed, so the matching depends on the kind of name:

- class and constant names match anywhere, as a whole word, case-sensitive;
- method and helper names match only as code: in backticks, or with `(`, `->`, `::` or `$`;
- test method names, commands, config keys and environment variables match anywhere;
- German words that were also class names (PROSE_WORDS) match only as code.

A third rule catches what neither list can see: a test method name quoted in a comment
(`testDieKennungStehtInSub`) that is declared nowhere in the tree. Such quotes name tests that
were replaced before epic 014, so they are not in the derived list either.

Excluded from a whole-tree run: vendor/, .an_framework/, an_project/work/ and
an_project/CHANGELOG.md (history), and tools/, whose own German names stay by decision of
epic 014 ("Nicht Teil des Epics").

Exit code 0 when nothing is found, 1 otherwise.
"""
import os
import re
import subprocess
import sys

BASE = 'b93e5cf7'  # the last commit before epic 014

CODE_PATHS = ['lib', 'custom', 'bin', 'tests', 'index.php', 'phpunit.xml.dist', 'tools', '.github']
MESSAGE_PATHS = ['lib', 'custom', 'bin', 'index.php']

EXTRA = {
    'Metadaten', 'anmeldeanbieter', 'loginbremse', 'postausgang.log', 'contentfly-feld',
    'appcms:provider:abgleich', 'uniq_user_fremdkennung', 'Anmeldebremse', 'Zugangstoken',
    'Anmeldetreiber', 'Tokenquellen',
}
PROSE_WORDS = {
    'Pfade', 'Metadaten', 'Artikel', 'Rubrik', 'IMMER', 'INTERN', 'MELDUNG', 'SCHLUESSEL',
    'TABELLE', 'PRAEFIX', 'NOETIG',
}
# Deliberate occurrences: [path, name, reason].
ALLOWED = [
    ('lib/contentfly/Classes/Security/FieldEncryption.php', 'contentfly-feld',
     'records the former key derivation context and when it changed (014-002-0003)'),
    ('tests/Unit/EnglishOnlyTest.php', 'Anmeldebremse', 'German input of the detector self-test'),
    ('tests/Unit/EnglishOnlyTest.php', 'istRefreshToken', 'German input of the detector self-test'),
    ('tests/Unit/EnglishOnlyTest.php', 'getKlartext', 'German input of the detector self-test'),
    ('tests/Unit/Security/JwtAccessTokenTest.php', 'gruppe', 'German claim name, forbidden on purpose'),
    ('tools/language/find-old-names.py', '*', 'this file lists the names it searches for'),
    ('an_project/docs/breaking-changes.md', 'einem Baum, der nicht mehr gilt.',
     'prose that happens to match an old message, not a quotation'),
    ('an_project/docs/breaking-changes.md', 'Sperrt Benutzer, die ihr Fremdsystem nicht mehr kennt',
     'prose describing appcms:provider:sync, not a quotation of its old description'),
]
EXCLUDE_PREFIXES = ('vendor/', '.an_framework/', 'an_project/work/', 'an_project/CHANGELOG.md', 'tools/')
BINARY = ('.png', '.jpg', '.gif', '.ico', '.woff', '.woff2', '.ttf', '.eot', '.svg', '.lock', '.phar')

NAME_PATTERNS = [
    r'\b(?:class|interface|trait)\s+(\w+)', r'function\s+&?(\w+)\s*\(', r'\bconst\s+(\w+)\s*=',
    r"\$app\['([\w.\-]+)'\]\s*=", r"setName\('([\w:\-]+)'\)", r"define\('(\w+)'", r"getenv\('(\w+)'\)",
    r'public \$(\w+)\s*=', r'\b(CONTENTFLY_\w+)',
]
GERMAN = re.compile(r'[äöüÄÖÜß]|\b(der|die|das|nicht|ist|wird|kein|keine|und|mit|für|fuer|ein|eine|zu|zum|zur|bitte)\b')


def git_files(rev, paths):
    out = subprocess.run(['git', 'ls-tree', '-r', '--name-only', rev, '--'] + paths,
                         capture_output=True, text=True, check=True).stdout
    return [f for f in out.split('\n') if f]


def git_show(rev, path):
    return subprocess.run(['git', 'show', f'{rev}:{path}'], capture_output=True, text=True).stdout


def names_at(rev):
    names = set()
    for f in git_files(rev, CODE_PATHS):
        if not f.endswith(('.php', '.sh', '.yml', '.xml', '.dist', '.json')):
            continue
        text = git_show(rev, f)
        for pattern in NAME_PATTERNS:
            names.update(re.findall(pattern, text))
        if f.endswith('.php'):
            names.add(f.rsplit('/', 1)[-1][:-4])
    return names


def messages_at(rev):
    found = set()
    for f in git_files(rev, MESSAGE_PATHS):
        if not f.endswith('.php'):
            continue
        code = re.sub(r'/\*.*?\*/|//[^\n]*', '', git_show(rev, f), flags=re.S)
        for m in re.finditer(r"'((?:[^'\\\n]|\\.){12,})'|\"((?:[^\"\\\n]|\\.){12,})\"", code):
            value = m.group(1) or m.group(2)
            if GERMAN.search(value):
                found.add(value)
    return found


def allowed(path, name):
    return any(path == p and (n == '*' or n == name) for p, n, _ in ALLOWED)


def search(files, names, messages):
    hits = []
    for path in files:
        try:
            text = open(path, encoding='utf-8', errors='replace').read()
        except (IsADirectoryError, FileNotFoundError):
            continue
        for lineno, line in enumerate(text.splitlines(), 1):
            code = ' '.join(re.findall(r'`([^`]*)`', line))
            for name in names:
                if name not in line or allowed(path, name):
                    continue
                esc = re.escape(name)
                if (name.startswith('test') and name[4:5].isupper()) or re.search(r'[:.\-]', name) \
                        or name.startswith(('CONTENTFLY_', 'SECURITY_')):
                    match = re.search(r'(?<![\w.:\-])' + esc + r'(?![\w:\-])', line)
                elif name[0].isupper():
                    if name in PROSE_WORDS:
                        match = re.search(r'(?<![\w\\])' + esc + r'\b', code) \
                            or re.search(r'\\' + esc + r'\b|\b' + esc + r'(::|\.php|\\)', line)
                    else:
                        match = re.search(r'(?<!\w)' + esc + r'\b', line)
                else:
                    match = re.search(r'(?<![\w$\-])' + esc + r'\b(?![.\-]\w)', code) \
                        or re.search(r'(->|::|\$)' + esc + r'\b|\b' + esc + r"\s*\(|\['" + esc + r"'\]", line)
                if match:
                    hits.append(f'{path}:{lineno}: name {name}: {line.strip()[:120]}')
        # Messages in code are the language guard's job (tests/Unit/EnglishOnlyTest.php); here
        # only quotations in documents count.
        if path.endswith('.md') and not allowed(path, '*'):
            for message in messages:
                for part in re.split(r'%[sd]|\{\w+\}|\\n|\$\w+', message):
                    part = part.strip()
                    if len(part) >= 18 and part in text and not allowed(path, part):
                        lineno = text[:text.index(part)].count('\n') + 1
                        hits.append(f'{path}:{lineno}: message: {part[:120]}')
    return hits


def undeclared_test_names(files):
    declared = set()
    for f in git_files('HEAD', ['lib', 'custom', 'bin', 'tests']):
        if f.endswith('.php'):
            declared.update(re.findall(r'function\s+(test[A-Z]\w*)\s*\(', open(f, errors='replace').read()))
    hits = []
    for path in files:
        if not path.endswith(('.php', '.md')) or allowed(path, '*'):
            continue
        for lineno, line in enumerate(open(path, errors='replace').read().splitlines(), 1):
            for name in re.findall(r'\b(test[A-Z]\w{6,})', line):
                if name not in declared and not re.search(r'function\s+' + name + r'\s*\(', line):
                    hits.append(f'{path}:{lineno}: undeclared test name {name}: {line.strip()[:120]}')
    return hits


def main():
    old_names = (names_at(BASE) - names_at('HEAD')) | EXTRA
    old_names = {n for n in old_names if len(n) > 3 and n != '__construct'}
    old_messages = messages_at(BASE) - messages_at('HEAD')

    if len(sys.argv) > 1:
        files = sys.argv[1:]
    else:
        files = [f for f in git_files('HEAD', ['.'])
                 if not f.startswith(EXCLUDE_PREFIXES) and not f.endswith(BINARY)]
        files += [f for f in subprocess.run(['git', 'ls-files', '--others', '--exclude-standard'],
                                            capture_output=True, text=True).stdout.split('\n')
                  if f and not f.startswith(EXCLUDE_PREFIXES) and not f.endswith(BINARY)]

    hits = search(files, old_names, old_messages) + undeclared_test_names(files)
    hits = sorted(set(hits))
    print('\n'.join(hits))
    print(f'{len(old_names)} old names, {len(old_messages)} old messages, {len(files)} files, '
          f'{len(hits)} hits', file=sys.stderr)
    return 1 if hits else 0


if __name__ == '__main__':
    sys.exit(main())
