@echo off

:: Get the directory of the script
SET HOOKS_DIR=%~dp0

:: Copy the hook to the .git/hooks/ directory
COPY "%HOOKS_DIR%\pre-commit-hook" "%HOOKS_DIR%\..\git\hooks\pre-commit"
CALLattrib +x "%HOOKS_DIR%\..\git\hooks\pre-commit"

ECHO Git hook installed successfully.
