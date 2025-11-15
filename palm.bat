@echo off
REM Palm CLI runner
REM Usage: palm <command> [arguments]

if "%~1"=="" (
    php "%~dp0app\scripts\palm.php"
) else (
    php "%~dp0app\scripts\palm.php" %*
)
exit /b %errorlevel%

:show_help
php "%~dp0app\scripts\palm.php"
exit /b %errorlevel%

