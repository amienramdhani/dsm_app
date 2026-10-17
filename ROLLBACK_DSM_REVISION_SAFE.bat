@echo off
setlocal enabledelayedexpansion
title DSM Safe Rollback

echo ============================================
echo DSM - SAFE ROLLBACK REVISION PACK
echo ============================================
echo.

if not exist "index.php" (
  echo [STOP] index.php tidak ditemukan di folder ini.
  echo Copy file ini ke C:\laragon\www\dsm lalu jalankan lagi.
  echo.
  pause
  exit /b 1
)

for /f %%i in ('powershell -NoProfile -Command "Get-Date -Format yyyyMMdd_HHmmss"') do set TS=%%i
set SAFE=_DSM_RECOVERY_BEFORE_ROLLBACK_%TS%

echo Membuat safety copy ke:
echo %CD%\%SAFE%
echo.
mkdir "%SAFE%" >nul 2>&1
mkdir "%SAFE%\lib" >nul 2>&1
mkdir "%SAFE%\assets" >nul 2>&1

REM Simpan kondisi SAAT INI dulu sebelum rollback.
for %%F in (
  "index.php"
  "api.php"
  "lib\dealer_brand.php"
  "lib\publish.php"
  "lib\core.php"
  "lib\api_import.php"
  "lib\api_kpikhusus.php"
  "assets\kpikhusus.js"
  "lib\portfolio_core.php"
  "lib\api_portfolio.php"
  "assets\portfolio_transfer.js"
  "assets\admin_revision.js"
  "assets\admin_revision.css"
) do (
  if exist "%%~F" (
    copy /Y "%%~F" "%SAFE%\%%~F" >nul
  )
)

echo [OK] Safety copy selesai.
echo.

set COUNT=0

REM Restore file existing dari backup .dsmrev.bak bila ada.
for %%F in (
  "index.php"
  "api.php"
  "lib\dealer_brand.php"
  "lib\publish.php"
  "lib\core.php"
  "lib\api_import.php"
  "lib\api_kpikhusus.php"
  "assets\kpikhusus.js"
) do (
  if exist "%%~F.dsmrev.bak" (
    echo [RESTORE] %%~F
    copy /Y "%%~F.dsmrev.bak" "%%~F" >nul
    if errorlevel 1 (
      echo [GAGAL] %%~F
      echo Kondisi sebelum rollback masih aman di %SAFE%
      pause
      exit /b 1
    )
    del /Q "%%~F.dsmrev.bak"
    set /a COUNT+=1
  ) else (
    echo [SKIP] Backup tidak ada: %%~F
  )
)

echo.
echo Menghapus file BARU dari revision pack jika ada...

for %%F in (
  "lib\portfolio_core.php"
  "lib\api_portfolio.php"
  "assets\portfolio_transfer.js"
  "assets\admin_revision.js"
  "assets\admin_revision.css"
) do (
  if exist "%%~F" (
    echo [DELETE] %%~F
    del /Q "%%~F"
    set /a COUNT+=1
  ) else (
    echo [SKIP] Tidak ada: %%~F
  )
)

echo.
echo ============================================
echo SELESAI - %COUNT% tindakan rollback dilakukan.
echo ============================================
echo Safety copy:
echo %CD%\%SAFE%
echo.
echo SEKARANG:
echo 1. Jangan Pull / Reset / Commit dulu.
echo 2. Jalankan DSM di Laragon.
echo 3. Buka VS Code - Source Control.
echo 4. Kirim screenshot Source Control ke ChatGPT.
echo.
pause
endlocal
