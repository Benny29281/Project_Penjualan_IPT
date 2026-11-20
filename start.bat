@echo off
setlocal
set "PHP_EXE="
for /f "delims=" %%P in ('where php.exe 2^>nul') do if not defined PHP_EXE set "PHP_EXE=%%P"
if not defined PHP_EXE for /d %%P in ("%~dp0..\..\bin\php\*") do if exist "%%~fP\php.exe" set "PHP_EXE=%%~fP\php.exe"
if not defined PHP_EXE (
  echo PHP tidak ditemukan. Tambahkan PHP Laragon ke PATH.
  exit /b 1
)
if not defined TOBAPIA_DATA_DIR set "TOBAPIA_DATA_DIR=%~dp0storage"
set "SQLITE=-d extension=pdo_sqlite"
"%PHP_EXE%" -m | findstr /x pdo_sqlite >nul
if not errorlevel 1 set "SQLITE="
cd /d "%~dp0"
if /i "%~1"=="owner" (
  "%PHP_EXE%" %SQLITE% app/console.php owner "%~2"
  exit /b
)
set "PORT=%~1"
if not defined PORT set "PORT=8080"
if not exist "%TOBAPIA_DATA_DIR%\sessions" mkdir "%TOBAPIA_DATA_DIR%\sessions"
echo Tobapia http://127.0.0.1:%PORT%/
"%PHP_EXE%" %SQLITE% -d "session.save_path=%TOBAPIA_DATA_DIR%\sessions" -d display_errors=0 -S 127.0.0.1:%PORT% router.php
