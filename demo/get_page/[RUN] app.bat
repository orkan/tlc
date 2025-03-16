@echo off

setlocal
set LOG_LEVEL=DEBUG
set LOG_VERBOSE=DEBUG
set LOG_EXTRAS=1
set LOG_FILE=app.log
set CACHE_KEEP=20 seconds

:start
echo ..............................................................................................
php app.php -vv
echo ..............................................................................................

choice /M Again
if %ERRORLEVEL% == 2 exit /B
goto :start
