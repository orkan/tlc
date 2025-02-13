@echo off

setlocal
set APP_DEBUG=1

echo.
echo 1. Run Flaresolverr.exe
start "FlareSolverr proxy server" "D:\Apps\Flaresolverr\flaresolverr.exe"
timeout /T 10

echo.
echo 2. Run PHP: TLC/Flaresolverr
php -f %~dp0app.php

echo.
echo 3. Kill Flaresolverr.exe
taskkill /IM flaresolverr.exe /F

echo.
timeout /T 10

