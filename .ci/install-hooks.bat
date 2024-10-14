@echo off

:: Get the directory of the script
SET HOOKS_DIR=%~dp0

REM Check if there isn't a .git directory
if not exist "%HOOKS_DIR%\..\\.git" (
    echo No .git directory found. Skipping installation.
    exit /b 0
)

REM Check if a git hook already exists
if exist "%HOOKS_DIR%\..\\.git\\hooks\\pre-commit" (
    echo Git hook already exists. Skipping installation.
    exit /b 0
)

:: Copy the hook to the .git/hooks/ directory
COPY "%HOOKS_DIR%\pre-commit-hook" "%HOOKS_DIR%\..\git\hooks\pre-commit"
CALLattrib +x "%HOOKS_DIR%\..\git\hooks\pre-commit"

ECHO Git hook installed successfully.
