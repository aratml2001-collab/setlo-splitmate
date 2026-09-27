@echo off
rem Build the production stylesheet (assets/css/app.css) from src/input.css
cd /d "%~dp0.."
tools\tailwindcss.exe -c tailwind.config.js -i src\input.css -o assets\css\app.css --minify
