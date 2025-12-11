@echo off
REM Wrapper for palm serve command with auto-open browser and live reload support
REM Usage: serve.bat [port] [--open] [--reload]
setlocal
set "SCRIPT_DIR=%~dp0"
set "PROJECT_ROOT=%SCRIPT_DIR%\.."

cd /d "%PROJECT_ROOT%"

if exist "%PROJECT_ROOT%\palm.bat" (
    REM Pass all arguments through to palm.bat serve
    call "%PROJECT_ROOT%\palm.bat" serve %*
) else (
    echo palm.bat not found in project root.
    exit /b 1
)

