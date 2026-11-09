@echo off
REM NISDAS SMS - make http://nisdasms.academy open the app on THIS computer.
REM Right-click this file -> "Run as administrator".
net session >nul 2>&1 || (echo Please right-click this file and choose "Run as administrator". & pause & exit /b 1)
set HOSTS=%SystemRoot%\System32\drivers\etc\hosts
findstr /C:"nisdasms.academy" "%HOSTS%" >nul 2>&1
if errorlevel 1 (
  copy /Y "%HOSTS%" "%HOSTS%.bak-nisdas" >nul
  >>"%HOSTS%" echo.
  >>"%HOSTS%" echo 127.0.0.1	nisdasms.academy	# NISDAS SMS ^(local^)
  >>"%HOSTS%" echo 127.0.0.1	www.nisdasms.academy	# NISDAS SMS ^(local^)
  echo Added nisdasms.academy to the hosts file.
) else (
  echo nisdasms.academy is already in the hosts file.
)
ipconfig /flushdns >nul
echo.
echo Checking the Apache configuration...
"C:\xampp\apache\bin\httpd.exe" -t
if errorlevel 1 (echo Apache configuration has an error - nothing restarted. Send this screen to Claude. & pause & exit /b 1)
sc query Apache2.4 >nul 2>&1
if errorlevel 1 (
  echo.
  echo Now open the XAMPP Control Panel and click Stop, then Start, next to Apache.
) else (
  net stop Apache2.4 >nul & net start Apache2.4
)
echo.
echo Done. Open http://nisdasms.academy/ in your browser.
pause
