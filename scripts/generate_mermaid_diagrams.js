#!/usr/bin/env node

/**
 * Generate SVG diagrams from Mermaid files
 * Usage: node generate_mermaid_diagrams.js [theme]
 *
 * Processes all .mmd files in code_snippets/<blog-post>/ directories
 * Outputs SVG files to www/generated/mermaid/<blog-post>/
 */

const fs = require('fs').promises;
const path = require('path');
const { execSync } = require('child_process');

// Config files
const CONFIG_FILE = path.join(__dirname, 'mermaid-config.json');
const PUPPETEER_CONFIG = path.join(__dirname, 'puppeteer-config.json');

async function processDirectory(baseDir, blogPost) {
    const snippetsDir = path.join(baseDir, 'code_snippets', blogPost);
    const outputDir = path.join(baseDir, 'www/generated', 'mermaid', blogPost);

    // Check for .mmd files
    const files = await fs.readdir(snippetsDir);
    const mmdFiles = files.filter(f => f.endsWith('.mmd'));

    if (mmdFiles.length === 0) {
        return 0;
    }

    // Create output directory
    await fs.mkdir(outputDir, { recursive: true });

    console.log(`\nProcessing: ${blogPost}`);

    let processed = 0;

    for (const file of mmdFiles) {
        const filePath = path.join(snippetsDir, file);
        const baseName = path.basename(file, '.mmd');
        const outputFile = path.join(outputDir, `${baseName}.svg`);

        console.log(`  Processing: ${file}`);

        try {
            // Run mmdc to generate SVG with config
            execSync(
                `npx mmdc -i "${filePath}" -o "${outputFile}" -c "${CONFIG_FILE}" -p "${PUPPETEER_CONFIG}" -b transparent`,
                { cwd: baseDir, stdio: 'pipe' }
            );

            console.log(`    -> ${outputFile}`);
            processed++;
        } catch (error) {
            console.error(`    Error processing ${file}:`, error.message);
        }
    }

    return processed;
}

async function generateDiagrams() {
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

        console.log(`Scanning ${directories.length} blog post(s) for .mmd files`);
        console.log(`Using config: ${CONFIG_FILE}`);

        let totalProcessed = 0;

        // Process each directory
        for (const blogPost of directories) {
            const count = await processDirectory(baseDir, blogPost);
            totalProcessed += count;
        }

        if (totalProcessed === 0) {
            console.log('\nNo .mmd files found in any blog post directory');
        } else {
            console.log(`\n✓ Mermaid diagram generation complete!`);
            console.log(`Generated ${totalProcessed} diagram(s)`);
        }

    } catch (error) {
        console.error('Error:', error.message);
        process.exit(1);
    }
}

// Run the generator
generateDiagrams();
