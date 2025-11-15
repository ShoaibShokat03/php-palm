@echo off
REM Wrapper for palm serve command
setlocal
set "SCRIPT_DIR=%~dp0"
set "PROJECT_ROOT=%SCRIPT_DIR%\.."

cd /d "%PROJECT_ROOT%"

if exist "%PROJECT_ROOT%\palm.bat" (
    call "%PROJECT_ROOT%\palm.bat" serve %*
) else (
    echo palm.bat not found in project root.
    exit /b 1
)

