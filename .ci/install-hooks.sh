#!/bin/bash

# Get the directory of the script
HOOKS_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

# Check if there isn't a .git directory
if [ ! -d "$HOOKS_DIR/../.git" ]; then
    echo "No .git directory found. Skipping installation."
    exit 0
fi

# Check if a git hook already exists
if [ -f "$HOOKS_DIR/../.git/hooks/pre-commit" ]; then
    echo "Git hook already exists. Skipping installation."
    exit 0
fi

# Copy the hook to the .git/hooks/ directory
cp "$HOOKS_DIR/pre-commit-hook" "$HOOKS_DIR/../.git/hooks/pre-commit"
chmod +x "$HOOKS_DIR/../.git/hooks/pre-commit"

echo "Git hook installed successfully."
