@echo off
rem DuKhachAnToan - Xoa sach du lieu va nap lai du lieu mau tu dau (dung khi du lieu bi sua loan trong luc thu nghiem).
rem CANH BAO: mat toan bo du lieu da nhap, anh da tai len va tai khoan da dang ky. Du lieu mau duoc tao lai tu dong.
chcp 65001 >nul
cd /d "%~dp0"
title DuKhachAnToan - nap lai du lieu
docker info >nul 2>&1
if errorlevel 1 (
    echo  [LOI] Docker Desktop chua chay.
    pause
    exit /b 1
)
echo  Thao tac nay XOA SACH co so du lieu va anh da tai len, roi nap lai du lieu mau.
choice /c YN /n /m "  Tiep tuc? [Y/N] "
if errorlevel 2 exit /b 0
docker compose down -v
if errorlevel 1 (
    echo  [LOI] Khong dung duoc container.
    pause
    exit /b 1
)
echo  Da xoa. Dang khoi dong lai va nap du lieu mau (khoang 2-3 phut)...
call chay.bat
