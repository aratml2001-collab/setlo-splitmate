@echo off
rem Rebuild assets/css/app.css on every change while developing
cd /d "%~dp0.."
tools\tailwindcss.exe -c tailwind.config.js -i src\input.css -o assets\css\app.css --watch
