ECHO OFF
setlocal ENABLEDELAYEDEXPANSION

CLS
ECHO.
ECHO     �������������������������������������������������������������ͻ
ECHO     �                                                             �
ECHO     �  Processing all examples (this may take a minute or two)    �
ECHO     �                                                             �
ECHO     �������������������������������������������������������������ͼ
ECHO.

FOR /F "tokens=1,2 delims= " %%G IN ('php -v 2^>NUL') DO (
 IF %%G==PHP SET PHPVersion=%%H
)

IF NOT DEFINED PHPVersion GOTO noPHP

ECHO     PHP binaries (%PHPVersion%) have been located
ECHO.
ECHO Processing examples : >temp\errors.log

SET /P Var="   Progress : "<NUL

FOR %%f IN (examples\example*.php) DO (
 SET /P Var=�<NUL
 ECHO %%f >>temp\errors.log
 php -q "%%f" 2>>&1>>temp\errors.log
)

ECHO.
ECHO.
ECHO     Examples rendered in the "temp\" folder.
ECHO.
GOTO end

:noPHP
ECHO.
ECHO     The PHP binaries can't be found!
ECHO.
ECHO     Examples rendering has been aborded.
:end
PAUSE >NUL