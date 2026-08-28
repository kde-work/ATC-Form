@echo off
setlocal EnableExtensions EnableDelayedExpansion

cd /d "%~dp0.."
if errorlevel 1 (
    echo Не удалось перейти в корень проекта.
    exit /b 1
)

set "HOST=rostudio.myjino.ru"
set "PORT=2222"
set "USER=rostudio"
set "REMOTE=/domains/express-consol.kutalo.com"

echo Файлы из последнего коммита для загрузки:
echo.

set "COUNT=0"
for /f "delimiters=" %%f in ('git diff-tree --no-commit-id --name-only -r HEAD -- app/') do (
    set /a COUNT+=1
    echo   %%f
    scp -P %PORT% "%%f" %USER%@%HOST%:%REMOTE%/%%f
    if errorlevel 1 (
        echo Ошибка загрузки: %%f
        exit /b 1
    )
)

if "!COUNT!"=="0" (
    echo В последнем коммите нет файлов из app/
    exit /b 0
)

echo.
echo Готово. Загружено файлов: !COUNT!
