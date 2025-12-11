@echo off
REM Palm CLI Launcher with Better Console Settings
REM This batch file sets up the console for optimal readability

REM Enable UTF-8 encoding for better character support (Windows 10+)
chcp 65001 >nul 2>&1

REM Set console window title
title PHP Palm CLI

REM Set console buffer and window size for better readability
REM Note: Font must be set manually via console properties
REM Recommended: Consolas or Lucida Console, size 12-14
REM To set font: Right-click title bar > Properties > Font tab
mode con: cols=100 lines=40

REM Clear screen for clean output
cls

REM Run palm.php with all arguments passed through
php "%~dp0app\scripts\palm.php" %*

REM Preserve exit code
exit /b %ERRORLEVEL%
