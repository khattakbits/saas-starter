import re
import sys
import unicodedata
from pathlib import Path

def check(path):
    p = Path(path)
    b = p.read_bytes()
    t = b.decode('utf-8')
    lines = t.split('\n')
    print(f'=== {path} ===')
    print('bytes:', len(b), '| lines:', len(lines) - 1)
    print('CRLF:', b.count(b'\r\n'), '| LF:', b.count(b'\n'))

    fences = [l for l in lines if l.strip().startswith('```')]
    print('fences:', len(fences), '| balanced:', len(fences) % 2 == 0)
    print('code blocks:', len(fences) // 2)

    in_f = False
    unlab = []
    for i, l in enumerate(lines, 1):
        if l.strip().startswith('```'):
            if not in_f and not l.strip()[3:].strip():
                unlab.append(i)
            in_f = not in_f
    print('unlabelled fences:', unlab or 'none')

    # backticked pipe inside a table row -> formatter makes a fake table
    bad = []
    for i, l in enumerate(lines, 1):
        if not l.lstrip().startswith('|'):
            continue
        parts = l.split('`')
        for j in range(1, len(parts) - 1, 2):
            if '|' in parts[j]:
                bad.append((i, parts[j][:60]))
    print('backticked pipes in tables:', bad or 'none')

    # table structure: header + separator + consistent columns
    in_f = False
    blocks, cur = [], []
    for i, l in enumerate(lines, 1):
        if l.strip().startswith('```'):
            in_f = not in_f
            if cur:
                blocks.append(cur); cur = []
            continue
        if in_f:
            continue
        if l.lstrip().startswith('|'):
            cur.append((i, l))
        else:
            if cur:
                blocks.append(cur); cur = []
    if cur:
        blocks.append(cur)

    sep = re.compile(r'^\|[\s:|-]+\|?\s*$')
    problems = []
    for blk in blocks:
        if len(blk) < 2:
            problems.append(('too short', blk[0][0]))
            continue
        second = blk[1][1].strip()
        if not (sep.match(second) and '-' in second):
            problems.append(('no separator', blk[0][0], blk[0][1][:60]))
        ncols = blk[0][1].count('|')
        for ln, txt in blk:
            if txt.count('|') != ncols:
                problems.append(('col mismatch', ln, txt.count('|'), ncols))
    print('table blocks:', len(blocks), '| problems:', problems or 'none')

    # homoglyphs
    hom = {(c, hex(ord(c))) for c in t
           if ord(c) > 127 and ('CYRILLIC' in unicodedata.name(c, '') or 'GREEK' in unicodedata.name(c, ''))}
    print('cyrillic/greek:', hom or 'clean')

    # internal section refs
    heads = set()
    for l in lines:
        m = re.match(r'^#{2,4}\s+(\d+(?:\.\d+)*)\.?\s', l)
        if m:
            heads.add(m.group(1))
    internal = set()
    in_f = False
    for l in lines:
        if l.strip().startswith('```'):
            in_f = not in_f
            continue
        if in_f:
            continue  # code block comments cross-ref nahi hain
        for m in re.finditer(r'(?<!`)(?<![\d.])\u00a7(\d+(?:\.\d+)*)', l):
            # `08` §2.9  -> external doc ref, is doc ka heading nahi
            if re.search(r'`(0[2-9]|1[01])`\s*$', l[:m.start()]):
                continue
            internal.add(m.group(1))
    missing = sorted(x for x in internal if x not in heads)
    print('headings:', len(heads), '| internal refs:', len(internal))

    if missing:
        # Har missing ref ke liye dekhein ke woh kisi DOOSRE doc ka heading hai
        external, truly = {}, []
        for ref in missing:
            where = [name for name, hs in DOC_INDEX.items()
                     if p.name != name and ref in hs]
            if where:
                external[ref] = sorted(where)
            else:
                truly.append(ref)
        if external:
            print('external refs (doosre doc ka heading):',
                  ', '.join(f'{r}->{"/".join(w)}' for r, w in external.items()))
        print('MISSING internal targets:', truly or 'none')
    else:
        print('MISSING internal targets: none')
    print()

def build_index():
    """docs/platform/*.md ke saare numeric headings -> {filename: set(refs)}"""
    index = {}
    for f in Path('docs/platform').glob('*.md'):
        hs = set()
        for l in f.read_text(encoding='utf-8').split('\n'):
            m = re.match(r'^#{2,4}\s+(\d+(?:\.\d+)*)\.?\s', l)
            if m:
                hs.add(m.group(1))
        index[f.name] = hs
    return index


DOC_INDEX = build_index()

for f in sys.argv[1:]:
    check(f)
