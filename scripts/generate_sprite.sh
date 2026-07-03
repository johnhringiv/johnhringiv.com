#!/bin/bash

# Icon sources, merged in order (later overrides earlier on name collision):
#   1. bootstrap-icons (node_modules)        — general UI glyphs
#   2. academicons   (jpswalsh/academicons)  — academic icons (e.g. google-scholar)
#   3. logos         (gilbarbara/logos)      — brand logos; this is svglogos.dev's backend
#   4. custom_icons/                         — hand-added / overrides (claude-icon, qwen-icon,
#                                              ollama-icon, reddit-icon, usenix, etc.)
#
# Another possible source for brand/AI-vendor logos: @lobehub/icons (a devDependency).
# It ships Color/Mono/Combine variants for Claude, Claude Code, Qwen, Ollama, LM Studio, etc.
# (the OG title card pulls Claude Code + Qwen marks from it). To add one to the sprite, export
# the variant's path(s) into a custom_icons/<name>.svg — see scripts/generate_titlecard_svg.mjs
# for how the paths are extracted from the package.

# Set variables
TEMP_DIR=".build/icons/academicons"
LOGOS_DIR=".build/icons/logos"
DEST_DIR=".build/svg"

if [ ! -d "$TEMP_DIR" ]; then
  # Clone repo (shallow clone for speed)
  git clone --depth 1 "https://github.com/jpswalsh/academicons.git" "$TEMP_DIR"
fi

if [ ! -d "$LOGOS_DIR" ]; then
  # Clone repo (shallow clone for speed)
  git clone --depth 1 "https://github.com/gilbarbara/logos" "$LOGOS_DIR"
fi

# Copy just the SVGs
mkdir -p "$DEST_DIR"

cp node_modules/bootstrap-icons/icons/*.svg "$DEST_DIR/"
cp "$TEMP_DIR/svg/"*.svg "$DEST_DIR/"
cp "$LOGOS_DIR/logos/"*.svg "$DEST_DIR/"
cp custom_icons/*.svg "$DEST_DIR/"

if [ "$1" = "prod" ]; then
  php scripts/generate-used-icons.php
  npx svg-sprite --symbol --dest=.build $(cat .build/used-icons.txt)
  rm .build/used-icons.txt
  else
    npx svg-sprite --symbol --dest=.build "$DEST_DIR/*.svg"
fi

mkdir -p www/generated
cp .build/symbol/svg/sprite.css.svg www/generated/sprite.svg

# Fix CSP violations: Remove all inline style attributes
# These cause CSP violations in Firefox (stricter than Chrome)
# Most SVG styles should use fill/stroke attributes instead
sed -i 's/ style="[^"]*"//g' www/generated/sprite.svg