/**
 * Rewrites `@core/...` imports inside the UI package to relative paths.
 *
 * Package ko host ke Vite alias par depend karna nahi chahiye — warna package
 * sirf usi app mein chalta hai jahan `@core` map ho. Ek dafa chalane wala
 * migration helper.
 *
 * Usage: node tools/relativize-core-imports.mjs [--write]
 */
import { readdirSync, readFileSync, statSync, writeFileSync, existsSync } from 'node:fs';
import { join, dirname, relative, extname } from 'node:path';

const ROOT = new URL('../../khattakbits-ui/src', import.meta.url).pathname.replace(/^\/([A-Za-z]:)/, '$1');
const WRITE = process.argv.includes('--write');

const files = [];
(function walk(dir) {
    for (const entry of readdirSync(dir)) {
        const full = join(dir, entry);
        if (statSync(full).isDirectory()) walk(full);
        else if (['.vue', '.js'].includes(extname(full))) files.push(full);
    }
})(ROOT);

const resolveTarget = (spec) => {
    const base = join(ROOT, spec);
    if (existsSync(base) && statSync(base).isFile()) return base;
    for (const ext of ['.js', '.vue']) {
        if (existsSync(base + ext)) return base + ext;
    }
    return null;
};

let changed = 0;
let total = 0;

for (const file of files) {
    const code = readFileSync(file, 'utf8');
    let next = code;
    let hits = 0;

    next = next.replace(/(['"])@core\/([^'"]+)\1/g, (match, quote, spec) => {
        const target = resolveTarget(spec);
        if (!target) {
            console.warn(`  ! unresolved: ${spec} in ${relative(ROOT, file)}`);
            return match;
        }
        let rel = relative(dirname(file), target).split('\\').join('/');
        if (!rel.startsWith('.')) rel = './' + rel;
        hits++;
        return `${quote}${rel}${quote}`;
    });

    if (hits > 0) {
        changed++;
        total += hits;
        console.log(`${WRITE ? 'WROTE' : 'DRY-RUN'} ${relative(ROOT, file).padEnd(42)} ${hits}`);
        if (WRITE) writeFileSync(file, next);
    }
}

console.log(`\n${WRITE ? 'WROTE' : 'DRY-RUN'}: ${total} import(s) in ${changed} file(s)`);
