import { globSync, rmSync } from 'node:fs';
import { basename, extname, resolve } from 'node:path';
import rtlcss from 'rtlcss';
import { defineConfig, type Plugin } from 'vite';

const assets = resolve(import.meta.dirname, 'public/assets');

const entries = globSync('modules/*/*/assets/{ts,scss}/*.{ts,scss}').filter((file) => !basename(file).startsWith('_') && !file.endsWith('.d.ts'));

const name = (file: string) => `${file.split(/[\\/]/)[2].toLowerCase()}-${basename(file, extname(file))}`;

const clean = (): Plugin => ({
    name: 'yawasla:clean',
    apply: 'build',
    buildStart() {
        for (const dir of ['css', 'js', '.vite']) {
            rmSync(resolve(assets, dir), { recursive: true, force: true });
        }
    },
});

const rtl = () => ({
    postcssPlugin: 'yawasla:rtl',
    Once(root: any, { postcss }: any) {
        if (root.source?.input.file?.endsWith('.rtl.scss')) {
            postcss([rtlcss()]).process(root, { from: undefined }).sync();
        }
    },
});

export default defineConfig(({ mode }) => ({
    base: './',
    publicDir: false,
    plugins: [clean()],
    css: {
        postcss: { plugins: [rtl()] },
        preprocessorOptions: {
            scss: { quietDeps: true, silenceDeprecations: ['import', 'global-builtin', 'color-functions', 'if-function'] },
        },
    },
    build: {
        outDir: assets,
        emptyOutDir: false,
        manifest: true,
        modulePreload: { polyfill: false },
        sourcemap: mode === 'development',
        minify: mode !== 'development',
        cssMinify: mode !== 'development',
        rolldownOptions: {
            input: Object.fromEntries(entries.map((file) => [`${name(file)}${extname(file)}`, file])),
            output: {
                entryFileNames: ({ name }) => `js/${name.replace(/\.ts$/, '')}-[hash].js`,
                chunkFileNames: 'js/[name]-[hash].js',
                assetFileNames: ({ names, originalFileNames }) => names[0]?.endsWith('.css') && originalFileNames[0] ? `css/${name(originalFileNames[0])}-[hash].css` : 'media/[name]-[hash][extname]',
            },
        },
    },
}));
