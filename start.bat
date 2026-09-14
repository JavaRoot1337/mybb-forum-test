@echo off
setlocal

chcp 65001 >nul
set "PROJECT_DIR=%~dp0"
cd /d "%PROJECT_DIR%"

set "HOST=127.0.0.1"
set "PORT=8080"
set "URL=http://%HOST%:%PORT%/"

if exist "%PROJECT_DIR%tools\runtime\php\php.exe" set "PATH=%PROJECT_DIR%tools\runtime\php;%PATH%"
if exist "%PROJECT_DIR%tools\runtime\composer\composer.bat" set "PATH=%PROJECT_DIR%tools\runtime\composer;%PATH%"

where php >nul 2>&1
if errorlevel 1 (
    echo PHP was not found in PATH.
    echo Install PHP 8.2 or newer and add it to PATH.
    pause
    exit /b 1
)

php -r "exit(version_compare(PHP_VERSION, '8.2.0', '>=') ? 0 : 1);"
if errorlevel 1 (
    echo PHP 8.2 or newer is required.
    php --version
    pause
    exit /b 1
)

if not exist "public\inc\vendor\autoload.php" (
    where composer >nul 2>&1
    if errorlevel 1 (
        echo Composer was not found in PATH.
        echo Install Composer and run this file again.
        pause
        exit /b 1
    )

    echo Installing project dependencies...
    call composer install --working-dir=config --no-interaction --prefer-dist
    if errorlevel 1 (
        echo Composer dependencies could not be installed.
        pause
        exit /b 1
    )
)

echo MyBB server: %URL%
echo Close the server window to stop it.
start "MyBB PHP server" /D "%PROJECT_DIR%" cmd /k "php -S %HOST%:%PORT% -t public"
timeout /t 2 /nobreak >nul
start "" "%URL%"

endlocal
