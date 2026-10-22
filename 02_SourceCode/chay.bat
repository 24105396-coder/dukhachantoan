@echo off
rem ============================================================
rem  DuKhachAnToan (CSE703073 DT-04) - Khoi dong he thong bang mot lenh
rem  Yeu cau: Windows 10/11 + Docker Desktop dang chay (Engine running)
rem  Cach dung: nhay dup chuot vao chay.bat hoac go  chay  trong PowerShell/CMD
rem ============================================================
setlocal EnableDelayedExpansion
chcp 65001 >nul
cd /d "%~dp0"
title DuKhachAnToan - khoi dong

echo.
echo  ===== DuKhachAnToan - Nen tang so hoa va dien giai di san van hoa da ngu =====
echo.

rem --- 1. Kiem tra Docker ------------------------------------------------
where docker >nul 2>&1
if errorlevel 1 (
    echo  [LOI] Khong tim thay lenh docker. Hay cai Docker Desktop: https://www.docker.com/products/docker-desktop/
    goto :fail
)
docker info >nul 2>&1
if errorlevel 1 (
    echo  [LOI] Docker Desktop chua chay. Hay mo Docker Desktop, cho trang thai "Engine running" roi chay lai.
    goto :fail
)
docker compose version >nul 2>&1
if errorlevel 1 (
    echo  [LOI] Docker Compose v2 khong kha dung. Hay cap nhat Docker Desktop.
    goto :fail
)

rem --- 2. Tao .env tu mau neu chua co ----------------------------------
if not exist ".env" (
    copy /y ".env.example" ".env" >nul
    echo  [OK] Da tao .env tu .env.example ^(gia tri mo phong cho moi truong hoc tap^).
)

rem --- 3. Doc cong web tu .env (mac dinh 8081) -------------------------
set "PORT=8081"
for /f "usebackq eol=# tokens=1,* delims==" %%a in (".env") do (
    if /i "%%a"=="APP_FORWARD_PORT" if not "%%b"=="" set "PORT=%%b"
)
set "URL=http://localhost:%PORT%"

rem --- 4. Canh bao neu cong da bi chiem boi tien trinh khac ------------
docker compose ps --status running -q 2>nul | findstr . >nul
if errorlevel 1 (
    netstat -ano | findstr /r /c:":%PORT% .*LISTENING" >nul 2>&1
    if not errorlevel 1 (
        echo  [CANH BAO] Cong %PORT% dang bi chuong trinh khac su dung.
        echo             Neu khoi dong that bai, mo .env va doi APP_FORWARD_PORT sang cong khac, vi du 8090.
        echo.
    )
)

rem --- 5. Dung va chay cac container -----------------------------------
echo  [1/3] Dang dung image va khoi dong 3 dich vu (db, analytics, app)...
echo        Lan dau can tai image va build, khoang 5-10 phut tuy toc do mang. Vui long cho.
echo.
docker compose up -d --build
if errorlevel 1 (
    echo.
    echo  [LOI] docker compose up that bai. Xem thong bao phia tren; thuong do cong bi chiem hoac mat mang khi tai image.
    goto :fail
)

rem --- 6. Cho ung dung san sang (entrypoint: cho MySQL, migrate, nap du lieu mau) ---
echo.
echo  [2/3] Dang cho ung dung san sang (lan dau nap du lieu mau mat khoang 2 phut)...
set /a TRIES=0
:wait
set /a TRIES+=1
set "CODE=0"
for /f "usebackq" %%s in (`powershell -NoProfile -Command "try { Invoke-WebRequest -UseBasicParsing -TimeoutSec 5 %URL%/healthz | Select-Object -ExpandProperty StatusCode } catch { 0 }"`) do set "CODE=%%s"
if "%CODE%"=="200" goto :ready
if %TRIES% geq 90 (
    echo.
    echo  [LOI] Sau 7 phut ung dung van chua phan hoi. Xem nhat ky bang lenh:  docker compose logs -f app
    goto :fail
)
<nul set /p "=."
timeout /t 5 /nobreak >nul
goto :wait

:ready
echo.
echo.
echo  [3/3] He thong da san sang.
echo.
echo   Trang web      : %URL%/vi      (tieng Anh: %URL%/en)
echo   Quan tri       : %URL%/vi/quan-tri
echo   Kiem tra song  : %URL%/healthz
echo   API mo         : %URL%/api/v1/openapi.json
echo.
echo   Tai khoan mau (mat khau: DuKhach@2026)
echo     admin@dukhach.test     Quan tri he thong
echo     manager@dukhach.test   Quan ly noi dung
echo     partner@dukhach.test   Doi tac (don vi quan ly di tich / nha cung cap tour)
echo     user@dukhach.test      Thanh vien
echo.
echo   Dung he thong  : chay dung.bat   (giu du lieu)
echo   Xoa sach du lieu de nap lai tu dau: docker compose down -v
echo.
start "" "%URL%/vi"
pause
exit /b 0

:fail
echo.
pause
exit /b 1
