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

echo Config: .vscode\sftp.json
echo Proto:  %PROTOCOL% %HOST%:%PORT%
echo Remote: %SFTP_USER%@%HOST%:%REMOTE%/
echo.
echo Files from last commit to upload:
echo.

set "COUNT=0"
for /f "delimiters=" %%f in ('git diff-tree --no-commit-id --name-only -r HEAD -- app/') do (
    set /a COUNT+=1
    echo   %%f
    if /i "%PROTOCOL%"=="ftp" (
        node "%~dp0_ftp-upload.cjs" "%%f" "%REMOTE%/%%f"
    ) else if /i "%PROTOCOL%"=="ftps" (
        node "%~dp0_ftp-upload.cjs" "%%f" "%REMOTE%/%%f"
    ) else if "!SFTP_KEY!"=="" (
        scp -P %PORT% -o StrictHostKeyChecking=accept-new "%%f" %SFTP_USER%@%HOST%:%REMOTE%/%%f
    ) else (
        scp -P %PORT% -o StrictHostKeyChecking=accept-new -i "!SFTP_KEY!" "%%f" %SFTP_USER%@%HOST%:%REMOTE%/%%f
    )
    if errorlevel 1 (
        echo Upload failed: %%f
        exit /b 1
    )
)

if "!COUNT!"=="0" (
    echo No app/ files in the last commit.
    exit /b 0
)

echo.
echo Done. Uploaded files: !COUNT!
