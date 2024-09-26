import { defineConfig } from 'vite';
import tailwindcss from 'tailwindcss';
import postcssImport from 'postcss-import';
import autoprefixer from 'autoprefixer';
import path from 'path';
import fs from 'fs';
import { glob } from 'glob';

function getJsEntries(dir) {
    const entries = {};
    const files = fs.readdirSync(dir);
    files.forEach(file => {
        if (file.endsWith('.js')) {
            const name = path.basename(file, '.js');
            entries[name] = path.resolve(dir, file);
        }
    });
    return entries;
}

const jsEntries = getJsEntries(path.resolve(__dirname, 'src/js'));

export default defineConfig({
    base: './',
    css: {
        postcss: {
            plugins: [
                postcssImport,
                tailwindcss,
                autoprefixer,
            ],
        },
    },
    build: {
        outDir: 'dist',
        sourcemap: false,
        rollupOptions: {
            input: {
                'main': './src/css/wicket-acc-main.css',
                'admin-main': './src/css/wicket-acc-admin-main.css',
                ...jsEntries,
            },
            output: {
                entryFileNames: 'assets/js/[name].js',
                chunkFileNames: 'assets/js/[name].js',
                assetFileNames: (assetInfo) => {
                    if (assetInfo.name.endsWith('.css')) {
                        return 'assets/css/wicket-acc-[name][extname]';
                    }
                    if (assetInfo.name.match(/\.(png|jpe?g|gif|svg|webp|avif)$/)) {
                        return 'assets/images/[name][extname]';
                    }
                    return 'assets/[name][extname]';
                },
            },
        },
        assetsInlineLimit: 4096, // 4kb
    },
    plugins: [
        {
            name: 'copy-images',
            async buildStart() {
                const images = await glob('src/images/**/*');
                for (const image of images) {
                    this.emitFile({
                        type: 'asset',
                        fileName: image.replace('src/', 'assets/'),
                        source: await fs.promises.readFile(image)
                    });
                }
            }
        },
        {
            name: 'add-index-html',
            generateBundle() {
                const indexContent = fs.readFileSync('src/index.html', 'utf-8');
                const directories = [
                    '',
                    'assets/',
                    'assets/css/',
                    'assets/js/',
                    'assets/images/'
                ];

                directories.forEach(dir => {
                    this.emitFile({
                        type: 'asset',
                        fileName: `${dir}index.html`,
                        source: indexContent
                    });
                });
            }
        }
    ]
});
