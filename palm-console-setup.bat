@echo off
REM Palm Console Setup - Configure console for optimal readability
REM Run this once to set up your console for Palm CLI commands

echo.
echo ========================================
echo  Palm Console Setup
echo ========================================
echo.
echo This script will help you configure your console
echo for better readability when using Palm CLI.
echo.
echo Recommended Font Settings:
echo   Font: Consolas or Lucida Console
echo   Size: 12-14 points
echo   Bold: Enabled (optional)
echo.
echo For Windows Console Properties:
echo   1. Right-click on console title bar
echo   2. Select "Properties"
echo   3. Go to "Font" tab
echo   4. Select "Consolas" or "Lucida Console"
echo   5. Set size to 12 or 14
echo   6. Click "OK"
echo.
echo ========================================
echo.

REM Try to set code page for UTF-8 support
chcp 65001 >nul 2>&1

REM Set console mode for better display
mode con: cols=100 lines=40

echo Console configured!
echo.
echo You can now use 'palm.bat' or 'php app\scripts\palm.php' to run Palm commands.
echo.
pause

