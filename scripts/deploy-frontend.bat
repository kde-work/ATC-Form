@echo off
setlocal EnableExtensions EnableDelayedExpansion
chcp 65001 >nul

cd /d "%~dp0.."
if errorlevel 1 (
    echo Failed to change directory to repo root.
    exit /b 1
)

call "%~dp0_load-sftp-config.bat"
if errorlevel 1 exit /b 1

set "LOCAL=frontend\dist\frontend\browser"
set "REMOTE_PUBLIC=%REMOTE%/public"

if not exist "%LOCAL%\index.html" (
    echo Build missing: %LOCAL%\index.html
    echo Build frontend first:
    echo   cd frontend
    echo   npm run build
    exit /b 1
)

echo Config: .vscode\sftp.json
echo Proto:  %PROTOCOL% %HOST%:%PORT%
echo Local:  %LOCAL%\
echo Remote: %SFTP_USER%@%HOST%:%REMOTE_PUBLIC%/
echo.
echo Files to upload:
dir /b "%LOCAL%"
echo.

echo Uploading browser/ contents to remote public/...
if /i "%PROTOCOL%"=="ftp" (
    node "%~dp0_ftp-upload.cjs" "%LOCAL%" "%REMOTE_PUBLIC%"
) else if /i "%PROTOCOL%"=="ftps" (
    node "%~dp0_ftp-upload.cjs" "%LOCAL%" "%REMOTE_PUBLIC%"
) else if "%SFTP_KEY%"=="" (
    scp -P %PORT% -o StrictHostKeyChecking=accept-new -r "%LOCAL%\*" %SFTP_USER%@%HOST%:%REMOTE_PUBLIC%/
) else (
    scp -P %PORT% -o StrictHostKeyChecking=accept-new -i "%SFTP_KEY%" -r "%LOCAL%\*" %SFTP_USER%@%HOST%:%REMOTE_PUBLIC%/
)
if errorlevel 1 (
    echo Frontend upload failed.
    exit /b 1
)

echo.
echo Done. Check remote index.html and hashed assets ^(main-*.js, styles-*.css^).
echo Old chunk-*.js files may remain on the server; remove manually if needed.
