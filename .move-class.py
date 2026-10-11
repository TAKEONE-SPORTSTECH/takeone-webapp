#!/usr/bin/env python3
"""
Move a PHP class between namespaces, repairing BOTH directions of the
implicit same-namespace reference trap.

The trap (CLAUDE.md, "Moving a class between modules"): siblings in one
namespace reference each other with no `use` statement, because they resolved
by proximity. After a move:

  1. files LEFT BEHIND that referenced the moved class bare now resolve to a
     class that is no longer there, and
  2. the MOVED file's own bare references to its former siblings now resolve
     into its new namespace.

Neither errors at autoload. Both fail later, when the relation is used.

Usage:  move-class.py <Class> <from-ns> <to-ns> <from-path> <to-path> [--apply]
"""
import os, re, subprocess, sys

cls, from_ns, to_ns, from_path, to_path = sys.argv[1:6]
apply = '--apply' in sys.argv
root = '/var/www/takeone'

def php_files():
    """
    Every file that can name a class.

    resources/ and the modules' own view folders are in here because a BLADE
    file can hold a fully-qualified class name — `\App\Models\ClubEvent::where(…)`
    inside an @php block — and leaving one behind does not fail at autoload or
    in any static check. It fails as a 500 from a COMPILED view in
    storage/framework/views, with a filename that says nothing about which
    blade it came from. Two of those were left behind before this was added.
    """
    for d in ('app','routes','tests','database','config','resources'):
        for dp,_,fns in os.walk(os.path.join(root,d)):
            for fn in fns:
                if fn.endswith('.php') or fn.endswith('.blade.php'):
                    yield os.path.join(dp,fn)

src = os.path.join(root, from_path)
dst = os.path.join(root, to_path)
if not os.path.exists(src):
    print(f"SKIP {cls}: {from_path} not found"); sys.exit(0)

# --- siblings that will be left behind in the old namespace ------------------
old_dir = os.path.dirname(src)
siblings = {f[:-4] for f in os.listdir(old_dir) if f.endswith('.php')} - {cls}

report = {'fqcn_updates': [], 'import_added_outside': [], 'import_added_inside': []}

# ---------------------------------------------------------------------------
# 1. The moved file: it may reference former siblings BARE. Add imports.
# ---------------------------------------------------------------------------
body = open(src).read()
# lambda, not a template: a replacement string would read the \E of
# App\Events as an escape sequence and raise.
body = re.sub(rf'^namespace\s+{re.escape(from_ns)}\s*;', lambda _: f'namespace {to_ns};', body, count=1, flags=re.M)

needed = set()
for sib in siblings:
    # a bare reference: Sib::class, Sib::, new Sib(, : Sib, |Sib, (Sib $
    if re.search(rf'(?<![\\\w]){re.escape(sib)}\s*::', body) or \
       re.search(rf'new\s+{re.escape(sib)}\s*\(', body) or \
       re.search(rf'[\(\|:,]\s*{re.escape(sib)}\s+\$', body):
        if not re.search(rf'^use\s+[\\\w]*\\{re.escape(sib)}\s*;', body, flags=re.M):
            needed.add(sib)

if needed:
    imports = ''.join(f'use {from_ns}\\{s};\n' for s in sorted(needed))
    m = re.search(r'^(namespace [^\n]+\n\n)', body, flags=re.M)
    if m:
        body = body[:m.end(1)] + imports + body[m.end(1):]
    report['import_added_inside'] = sorted(needed)

# ---------------------------------------------------------------------------
# 2. Everything else: rewrite the FQCN, and repair left-behind bare refs.
# ---------------------------------------------------------------------------
edits = {}
for f in php_files():
    if os.path.abspath(f) == os.path.abspath(src):
        continue
    txt = open(f).read()
    orig = txt

    # a. fully-qualified references anywhere
    txt = re.sub(rf'(?<![\w]){re.escape(from_ns)}\\{re.escape(cls)}(?![\w])',
                 lambda _: f'{to_ns}\\{cls}', txt)

    # b. a file that LIVED beside it and referenced it bare now needs an import
    m = re.search(r'^namespace\s+([^\s;]+)\s*;', txt, flags=re.M)
    ns = m.group(1) if m else None
    if ns == from_ns:
        bare = (re.search(rf'(?<![\\\w]){re.escape(cls)}\s*::', txt) or
                re.search(rf'new\s+{re.escape(cls)}\s*\(', txt) or
                re.search(rf'[\(\|:,]\s*{re.escape(cls)}\s+\$', txt))
        has_use = re.search(rf'^use\s+[\\\w]*\\{re.escape(cls)}\s*;', txt, flags=re.M)
        if bare and not has_use:
            mm = re.search(r'^(namespace [^\n]+\n\n)', txt, flags=re.M)
            if mm:
                txt = txt[:mm.end(1)] + f'use {to_ns}\\{cls};\n' + txt[mm.end(1):]
                report['import_added_outside'].append(os.path.relpath(f, root))

    if txt != orig:
        edits[f] = txt
        report['fqcn_updates'].append(os.path.relpath(f, root))

# ---------------------------------------------------------------------------
print(f"\n=== {cls}: {from_ns} -> {to_ns} ===")
print(f"  files touched      : {len(edits)}")
if report['import_added_inside']:
    print(f"  imports ADDED to the moved file (former siblings): {', '.join(report['import_added_inside'])}")
if report['import_added_outside']:
    print(f"  imports ADDED to left-behind siblings: {', '.join(report['import_added_outside'])}")

if not apply:
    print("  (dry run — nothing written)")
    sys.exit(0)

os.makedirs(os.path.dirname(dst), exist_ok=True)
subprocess.run(['git','mv',from_path,to_path], cwd=root, check=True)
open(dst,'w').write(body)
for f,txt in edits.items():
    open(f,'w').write(txt)
print("  APPLIED")
