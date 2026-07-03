#!/usr/bin/env node

/**
 * Generate syntax-highlighted HTML using Shiki
 * Usage: node generate_shiki_highlights.mjs [theme]
 *
 * Uses shiki-class-transformer to extract inline styles to CSS classes for lighter output.
 * More grammars can be found here: https://github.com/github-linguist/linguist/tree/main/vendor/grammars
 */

import fs from 'fs/promises';
import path from 'path';
import { createHighlighter } from 'shiki';
import { shikiClassTransformer } from 'shiki-class-transformer';
import CSON from 'cson';
import { createRequire } from 'module';

const require = createRequire(import.meta.url);

// Configuration
const THEMES = {
    'monokai': 'monokai',
    'github-dark': 'github-dark',
    'github-dark-default': 'github-dark-default',
    'github-dark-dimmed': 'github-dark-dimmed',
    'dracula': 'dracula',
    'nord': 'nord',
    'one-dark-pro': 'one-dark-pro',
    'vitesse-dark': 'vitesse-dark',
    'gruvbox-dark-hard': 'gruvbox-dark-hard',
    'gruvbox-dark-medium': 'gruvbox-dark-medium',
    'gruvbox-dark-soft': 'gruvbox-dark-soft',
};

// Map themes to their shiki-class-transformer JSON maps
const THEME_MAPS = {
    'monokai': () => require('shiki-class-transformer/themes/monokai.json'),
    'github-dark': () => require('shiki-class-transformer/themes/github-dark.json'),
    'github-dark-default': () => require('shiki-class-transformer/themes/github-dark-default.json'),
    'github-dark-dimmed': () => require('shiki-class-transformer/themes/github-dark-dimmed.json'),
    'dracula': () => require('shiki-class-transformer/themes/dracula.json'),
    'nord': () => require('shiki-class-transformer/themes/nord.json'),
    'one-dark-pro': () => require('shiki-class-transformer/themes/one-dark-pro.json'),
    'vitesse-dark': () => require('shiki-class-transformer/themes/vitesse-dark.json'),
};

const LANGUAGE_MAP = {
    '.c': 'c',
    '.h': 'c',
    '.rs': 'rust',
    '.asm': 'asm',
    '.s': 'asm',
    '.py': 'python',
    '.js': 'javascript',
    '.ts': 'typescript',
    '.json': 'json',
    '.md': 'markdown',
    '.java': 'java',
    '.sh': 'bash',
    '.bash': 'bash',
    '.yaml': 'yaml',
    '.ebnf': 'ebnf',
    '.ast': 'ast-tree',
    '.tokens': 'tokens',
    '.nginx': 'nginx',
    '.conf': 'nginx',
    '.prompt': 'llm-prompt',
};

// File extensions to skip (handled by other generators)
const SKIP_EXTENSIONS = ['.mmd'];

// Custom transformer to add line numbers and pre styling via classes
function createLineNumberTransformer(noLineNumbers) {
    return {
        pre(node) {
            // Add class for pre element styling (CSS will handle the actual styles)
            node.properties.class = (node.properties.class || '') + ' shiki-pre';
            // Remove any inline style that shiki adds
            delete node.properties.style;
        },
        line(node, line) {
            if (!noLineNumbers) {
                node.children = [
                    {
                        type: 'element',
                        tagName: 'span',
                        properties: { class: 'ln' },
                        children: [{ type: 'text', value: String(line) }]
                    },
                    ...node.children
                ];
            }
        }
    };
}

async function processDirectory(baseDir, blogPost, theme, highlighter, themeMap) {
    const snippetsDir = path.join(baseDir, 'code_snippets', blogPost);
    const outputDir = path.join(baseDir, 'www/generated', 'highlighted-shiki', blogPost);

    // Create output directory
    await fs.mkdir(outputDir, { recursive: true });

    console.log(`\nProcessing: ${blogPost}`);

    // Process each snippet
    const files = await fs.readdir(snippetsDir);

    for (const file of files) {
        const filePath = path.join(snippetsDir, file);
        const stats = await fs.stat(filePath);

        if (stats.isFile()) {
            const ext = path.extname(file);

            // Skip files handled by other generators
            if (SKIP_EXTENSIONS.includes(ext)) {
                continue;
            }

            const baseName = path.basename(file, ext);
            const language = LANGUAGE_MAP[ext] || 'text';

            console.log(`  Processing: ${file} (language: ${language})`);

            // Read the code
            let code = await fs.readFile(filePath, 'utf8');

            // Check for shiki: nolinenum directive and remove it
            const noLineNumbers = /\/\/\s*shiki:\s*nolinenum\s*\n?|#\s*shiki:\s*nolinenum\s*\n?|\/\*\s*shiki:\s*nolinenum\s*\*\/\s*\n?/.test(code);

            // Remove the shiki directive from the code
            const cleanCode = code.replace(/\/\/\s*shiki:\s*nolinenum\s*\n?|#\s*shiki:\s*nolinenum\s*\n?|\/\*\s*shiki:\s*nolinenum\s*\*\/\s*\n?/g, '');

            // Generate highlighted HTML with class transformer and custom line numbers
            let html = highlighter.codeToHtml(cleanCode, {
                lang: language,
                theme: THEMES[theme] || 'vitesse-dark',
                transformers: [
                    shikiClassTransformer({ map: themeMap }),
                    createLineNumberTransformer(noLineNumbers)
                ]
            });

            // Encode the clean code (without directive) in base64 for safe embedding
            const encodedCode = Buffer.from(cleanCode).toString('base64');

            // Wrap in a container with a copy button (using classes instead of inline styles)
            const wrappedHtml = `<div class="shiki-container">
${html}
<button class="shiki-copy" data-action="copy" data-code="${encodedCode}" aria-label="Copy code to clipboard">Copy</button>
</div>`;

            // Save the output
            const outputFile = path.join(outputDir, `${baseName}.html`);
            await fs.writeFile(outputFile, wrappedHtml);

            console.log(`    -> ${outputFile}`);
        }
    }
}

async function generateHighlights(theme = 'vitesse-dark') {
    const baseDir = process.cwd();
    const codeSnippetsDir = path.join(baseDir, 'code_snippets');

    try {
        // Check if code_snippets directory exists
        await fs.access(codeSnippetsDir);

        // Get all directories in code_snippets
        const entries = await fs.readdir(codeSnippetsDir, { withFileTypes: true });
        const directories = entries.filter(entry => entry.isDirectory()).map(entry => entry.name);

        if (directories.length === 0) {
            console.log('No directories found in code_snippets/');
            return;
        }

        console.log(`Found ${directories.length} blog post(s) to process`);
        console.log(`Using theme: ${theme}`);

        // Load theme map for shiki-class-transformer
        const themeMapLoader = THEME_MAPS[theme];
        if (!themeMapLoader) {
            console.warn(`Warning: No class transformer map for theme "${theme}", using vitesse-dark`);
        }
        const themeMap = themeMapLoader ? themeMapLoader() : THEME_MAPS['vitesse-dark']();

        // Lazy-load EBNF grammar - fetch from GitHub or use cached version
        const tmpDir = path.join(baseDir, '.build', 'cache');
        const cachedEbnfPath = path.join(tmpDir, 'ebnf.cson');
        let ebnfCsonContent;

        try {
            // Try to use cached version first
            ebnfCsonContent = await fs.readFile(cachedEbnfPath, 'utf8');
            console.log('Using cached EBNF grammar from .build/cache/');
        } catch (err) {
            // Fetch from GitHub if not cached
            console.log('Fetching EBNF grammar from GitHub...');
            const ebnfUrl = 'https://raw.githubusercontent.com/Alhadis/language-grammars/daa8548cc820359b078192dc85b6c8845e9db54c/grammars/ebnf.cson';
            const response = await fetch(ebnfUrl);
            if (!response.ok) {
                throw new Error(`Failed to fetch EBNF grammar: ${response.statusText}`);
            }
            ebnfCsonContent = await response.text();

            // Cache it for future use
            await fs.mkdir(tmpDir, { recursive: true });
            await fs.writeFile(cachedEbnfPath, ebnfCsonContent);
            console.log('Cached EBNF grammar to .build/cache/');
        }

        const ebnfGrammar = CSON.parse(ebnfCsonContent);

        // Load custom grammars from scripts/grammars/
        const grammarsDir = path.join(baseDir, 'scripts', 'grammars');
        const astTreeGrammar = JSON.parse(await fs.readFile(path.join(grammarsDir, 'ast-tree.json'), 'utf8'));
        const tokensGrammar = JSON.parse(await fs.readFile(path.join(grammarsDir, 'tokens.json'), 'utf8'));
        const llmPromptGrammar = JSON.parse(await fs.readFile(path.join(grammarsDir, 'llm-prompt.json'), 'utf8'));

        // Initialize Shiki highlighter once for all directories
        const highlighter = await createHighlighter({
            themes: [THEMES[theme] || 'vitesse-dark'],
            langs: [
                'c', 'rust', 'python', 'javascript', 'typescript', 'json', 'markdown', 'bash', 'asm', 'yaml', 'java', 'nginx',
                {
                    ...ebnfGrammar,
                    name: 'ebnf',
                    scopeName: ebnfGrammar.scopeName || 'source.ebnf'
                },
                astTreeGrammar,
                tokensGrammar,
                llmPromptGrammar
            ]
        });

        // Process each directory
        for (const blogPost of directories) {
            await processDirectory(baseDir, blogPost, theme, highlighter, themeMap);
        }

        console.log('\n✓ Shiki highlight generation complete!');
        console.log(`Processed ${directories.length} blog post(s)`);
        console.log(`\nRemember to include the CSS file: www/css/shiki.css`);

    } catch (error) {
        console.error('Error:', error.message);
        console.error(error.stack);
        process.exit(1);
    }
}

// Parse command line arguments
const args = process.argv.slice(2);
const theme = args[0] || 'vitesse-dark';

if (args.length > 0 && !THEMES[args[0]]) {
    console.log('Usage: node generate_shiki_highlights.mjs [theme]');
    console.log('Available themes:', Object.keys(THEMES).join(', '));
    console.log('Example: node generate_shiki_highlights.mjs vitesse-dark');
    console.log('\nNote: This will process all directories in code_snippets/');
    process.exit(1);
}

// Run the generator
generateHighlights(theme);
