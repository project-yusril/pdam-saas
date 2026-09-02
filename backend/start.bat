@echo off
setlocal
cd /d "%~dp0"

echo ============================================================
echo   PDAM SaaS - Backend (Laravel 13) + Frontend (Vite)
echo ============================================================
echo.

REM ---------- 1. Cek PHP ----------
where php >nul 2>nul
if errorlevel 1 (
    echo [ERROR] PHP tidak ditemukan di PATH.
    echo         Silakan instal PHP dan tambahkan ke PATH terlebih dahulu.
    pause
    exit /b 1
)

REM ---------- 2. Cek vendor (composer) ----------
if not exist "vendor\autoload.php" (
    echo [INFO] Dependensi backend belum ada. Menjalankan composer install...
    composer install --no-interaction
    if errorlevel 1 (
        echo [ERROR] composer install gagal. Periksa koneksi/PHP Anda.
        pause
        exit /b 1
    )
)

REM ---------- 3. Cek .env ----------
if not exist ".env" (
    if exist ".env.example" (
        echo [INFO] .env belum ada. Menyalin dari .env.example...
        copy ".env.example" ".env" >nul
        echo [INFO] Jangan lupa buat APP_KEY:  php artisan key:generate
        echo.
    ) else (
        echo [WARN] .env dan .env.example tidak ditemukan.
    )
)

REM ---------- 4. Jalankan Vite (frontend) di jendela terpisah ----------
if exist "package.json" (
    if not exist "node_modules" (
        echo [INFO] Dependensi frontend belum ada. Menjalankan npm install...
        call npm install
    )
    start "PDAM SaaS - Vite (frontend)" cmd /k "npm run dev"
)

echo.
echo   Pilih data demo (tenant PDAM):
echo     admin_tenant@gmail.com / 12345678   (kode PDAM: pdam-canada atau pdam-brazil)
echo     superadmin@gmail.com / 12345678     (mode Super Admin)
echo ============================================================
echo   Backend  : http://127.0.0.1:8000
echo   Frontend : http://localhost:5173
echo ============================================================
echo   Tekan Ctrl+C untuk menghentikan backend.
echo.

php artisan serve --host=127.0.0.1 --port=8000

endlocal
