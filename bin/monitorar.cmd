@echo off
setlocal
cd /d "%~dp0.."
echo Monitor local. Interrompa com Ctrl+C. Nao abra duas copias.
:repetir
"C:\xampp\php\php.exe" bin\monitor.php
timeout /t 60 /nobreak >nul
goto repetir
