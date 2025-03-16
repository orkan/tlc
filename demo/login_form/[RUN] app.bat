@echo off

setlocal
set LOG_LEVEL=DEBUG
set LOG_VERBOSE=DEBUG
set LOG_EXTRAS=1
set LOG_FILE=app.log
set CACHE_KEEP=0
set OPTIONS=

:start
echo ..............................................................................................
php app.php -v %OPTIONS%
echo ..............................................................................................

choice /C AQRN /N /M "[A]gain, [Q]uit, [R]eset, Invalid [N]once:"
if %ERRORLEVEL% == 1 goto :again
if %ERRORLEVEL% == 2 exit /B
if %ERRORLEVEL% == 3 goto :reset
if %ERRORLEVEL% == 4 goto :nonce

:again
set OPTIONS=
goto :start

:reset
set OPTIONS=--reset
goto :start

:nonce
set OPTIONS=--nonce
goto :start
