@echo off

setlocal
set "ADDR=localhost:8000"
set "ROOT=%~dp0"

echo **********************************************************************************************
echo NAME: PHP Development server
echo ADDR: "%ADDR%"
echo ROOT: "%ROOT%"
echo **********************************************************************************************
echo.

php -S %ADDR% -t "%ROOT%"
