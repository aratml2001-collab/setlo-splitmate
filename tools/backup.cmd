@echo off
rem Back up Setlo: dump the database to backups\ and commit the code to git.
rem Usage: tools\backup.cmd            (commit message "Backup <date>")
rem        tools\backup.cmd "message"  (your own commit message)
cd /d "%~dp0.."
for /f %%i in ('powershell -NoProfile -Command "Get-Date -Format yyyy-MM-dd_HHmm"') do set STAMP=%%i
if not exist backups mkdir backups
rem The folder is inside htdocs - never let Apache serve the dumps
if not exist backups\.htaccess echo Require all denied> backups\.htaccess

"C:\xampp\mysql\bin\mysqldump.exe" -u root --routines --single-transaction setlo > "backups\setlo_%STAMP%.sql"
if errorlevel 1 (
  echo Database backup FAILED - is MySQL running in the XAMPP Control Panel?
  del "backups\setlo_%STAMP%.sql" 2>nul
  exit /b 1
)
echo Database saved to backups\setlo_%STAMP%.sql

set MSG=%~1
if "%MSG%"=="" set MSG=Backup %STAMP%
git add -A
git commit -q -m "%MSG%" && echo Code committed: %MSG% || echo No code changes since the last backup.
