@echo off
rem DuKhachAnToan - Dung toan bo container (giu nguyen du lieu trong volume).
rem Muon xoa sach du lieu de lan sau nap lai tu dau: docker compose down -v
chcp 65001 >nul
cd /d "%~dp0"
title DuKhachAnToan - dung

docker info >nul 2>&1
if errorlevel 1 (
    echo  [LOI] Docker Desktop chua chay nen khong co gi de dung.
    pause
    exit /b 1
)
echo  Dang dung DuKhachAnToan...
docker compose down
if errorlevel 1 (
    echo  [LOI] Dung that bai, xem thong bao phia tren.
    pause
    exit /b 1
)
echo  Da dung. Du lieu van duoc giu; chay chay.bat de khoi dong lai.
pause
exit /b 0
