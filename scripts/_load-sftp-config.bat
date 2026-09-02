@echo off
rem Loads HOST, PORT, SFTP_USER, REMOTE, SFTP_KEY from .vscode/sftp.json
rem and configures SSH_ASKPASS for password auth.

set "SFTP_JSON=%~dp0..\.vscode\sftp.json"
if not exist "%SFTP_JSON%" (
    echo Missing %SFTP_JSON%
    exit /b 1
)

where node >nul 2>nul
if errorlevel 1 (
    echo Node.js is required to read sftp.json
    exit /b 1
)

set "HOST="
set "PORT="
set "SFTP_USER="
set "REMOTE="
set "SFTP_KEY="

for /f "usebackq delims=" %%a in (`node "%~dp0_read-sftp-config.cjs"`) do (
    for /f "tokens=1* delims==" %%b in ("%%a") do (
        set "%%b=%%c"
    )
)

if not defined HOST (
    echo Failed to parse sftp.json
    exit /b 1
)
if not defined SFTP_USER (
    echo Failed to parse sftp.json username
    exit /b 1
)
if not defined REMOTE (
    echo Failed to parse sftp.json remotePath
    exit /b 1
)
if not defined PORT set "PORT=22"

set "SSH_ASKPASS=%~dp0_sftp-askpass.cmd"
set "SSH_ASKPASS_REQUIRE=force"
set "DISPLAY=1"
