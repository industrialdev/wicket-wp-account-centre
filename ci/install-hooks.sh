#!/bin/bash

# Get the directory of the script
HOOKS_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

# Copy the hook to the .git/hooks/ directory
cp "$HOOKS_DIR/pre-commit-hook" "$HOOKS_DIR/../.git/hooks/pre-commit"
chmod +x "$HOOKS_DIR/../.git/hooks/pre-commit"

echo "Git hook installed successfully."
