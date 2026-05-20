import { execFileSync } from 'node:child_process';
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const sourcePath = resolve(root, 'resources/css/preview.css');
const safelistPath = resolve(root, 'resources/tailwind/safelist.txt');
const cacheDir = resolve(root, 'storage/framework/cache');
const inputPath = resolve(cacheDir, 'preview-tailwind.input.css');
const outputPath = resolve(root, 'public/preview.css');
const tailwindCli = resolve(root, 'node_modules/@tailwindcss/cli/dist/index.mjs');

const safelist = readFileSync(safelistPath, 'utf8')
    .split(/\r?\n/)
    .map((line) => line.trim())
    .filter((line) => line !== '' && !line.startsWith('#'))
    .join(' ');

const source = readFileSync(sourcePath, 'utf8').replace('__PREVIEW_SAFELIST__', safelist);

mkdirSync(cacheDir, { recursive: true });
writeFileSync(inputPath, source);

execFileSync(process.execPath, [tailwindCli, '-i', inputPath, '-o', outputPath, '--minify'], {
    cwd: root,
    stdio: 'inherit',
});
