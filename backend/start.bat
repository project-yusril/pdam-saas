@echo off
REM ============================================================
REM  PDAM SaaS - Backend Launcher
REM  Klik 2x file ini untuk menjalankan server Laravel.
REM ============================================================

cd /d "%~dp0"
title PDAM Backend - php artisan serve

echo ============================================================
echo   PDAM SaaS - Menjalankan Backend Laravel
echo   Server : http://127.0.0.1:8000
echo   API    : http://127.0.0.1:8000/api/v1
echo   Docs   : http://127.0.0.1:8000/api/documentation
echo   Stop   : tekan CTRL+C
echo ============================================================
echo.

php artisan serve

echo.
echo ------------------------------------------------------------
echo Server berhenti.
pause
