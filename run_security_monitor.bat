@echo off
echo NCL Security Monitor - Windows Task Scheduler Script
echo =====================================================
echo.

cd /d "C:\xampp\htdocs\ncl-league-platform"

echo Starting security monitoring...
php admin\security_monitor.php

echo.
echo Security monitoring completed.
echo Next run recommended in 1 hour.

REM Uncomment the next line to create a simple log
REM echo %date% %time% - Security monitor completed >> security_monitor.log

pause
