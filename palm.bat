@echo off
REM Palm CLI Launcher with Verified Setup
REM This batch file ensures the environment is ready before running Palm

REM Enable delayed expansion
setlocal EnableDelayedExpansion

REM Enable UTF-8 encoding
chcp 65001 >nul 2>&1

REM Set styling: Title and Size
title PHP Palm CLI - World's Super Framework
REM Increased buffer size: 120 columns, 3000 lines for scrollable output
mode con: cols=120 lines=3000

REM Check if PHP is in PATH
php -v >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo.
    echo [ERROR] PHP is not found in your PATH.
    echo Please install PHP and add it to your system PATH variables.
    echo.
    pause
    exit /b 1
)

REM Optional: Clear screen to start fresh
REM Commented out to preserve scrollable history
REM cls

REM Run the main Palm PHP script
REM "%~dp0" resolves to the directory of this batch file
php "%~dp0app\scripts\palm.php" %*

REM Preserve exit code from PHP script
exit /b %ERRORLEVEL%
