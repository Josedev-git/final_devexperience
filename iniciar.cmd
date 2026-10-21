@echo off
setlocal
cd /d "%~dp0"
set "SENN_PHP=C:\xampp\php\php.exe"
if not exist "%SENN_PHP%" (
 echo PHP nao encontrado em C:\xampp\php. Edite SENN_PHP neste arquivo.
 pause
 exit /b 1
)
if not exist ".env" copy ".env.xampp.example" ".env" >nul
echo Ligue o MySQL no XAMPP e confira o banco senn_aix.
echo Abra http://localhost:8080/index.php?page=login
echo Mantenha esta janela aberta. Para parar, pressione Ctrl+C.
"%SENN_PHP%" -S localhost:8080 -t public public/router.php
pause
