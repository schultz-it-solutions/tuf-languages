@echo off
rem ---------------------------------------------------------------------------
rem lang-download.bat - copy the translations from
rem github.com/schultz-it-solutions/tuf-languages into the TUF_ED package source.
rem
rem Updates the local clone, then writes language\<tag>\*.ini into the package folder
rem and updates the <languages> block of pkg_tuf_ed.xml (between the
rem "tuf-languages: begin/end" markers; your own <language> lines stay as they are).
rem Security strings that no native speaker has reviewed yet are written in English.
rem Warns if your local en-GB is newer than the repository (run lang-upload.bat).
rem
rem Environment:
rem   TUF_ED_ROOT      (required) your TUF_ED source folder
rem   TUFLANG_PKG      (required) the package folder that contains pkg_tuf_ed.xml
rem   TUFLANG_EXCLUDE  languages you maintain yourself, comma separated
rem                    (default: de-DE; "none" to take all languages from the repository)
rem   TUFLANG_DIR      local clone of tuf-languages (default: %TUF_ED_ROOT%\..\tuf-languages,
rem                    cloned on first use)
rem   TUFLANG_VERSION  version line in the repository (default: v0)
rem   TUFLANG_STRICT   set to 1 to stop with an error (instead of a warning) when the
rem                    local en-GB differs from the repository, e.g. for release builds
rem   PHP              PHP command line binary (default: php)
rem Exit code 0 = done, 1 = error.
rem ---------------------------------------------------------------------------
setlocal EnableExtensions
if not defined TUF_ED_ROOT (
	echo ERROR: set TUF_ED_ROOT to your TUF_ED source folder.
	exit /b 1
)
if not defined TUFLANG_PKG (
	echo ERROR: set TUFLANG_PKG to the folder that contains pkg_tuf_ed.xml.
	exit /b 1
)
if not defined TUFLANG_DIR set "TUFLANG_DIR=%TUF_ED_ROOT%\..\tuf-languages"
if not defined TUFLANG_VERSION set "TUFLANG_VERSION=v0"
if not defined TUFLANG_EXCLUDE set "TUFLANG_EXCLUDE=de-DE"
if not defined PHP set "PHP=php"
set "EXCLUDE_ARG=--exclude=%TUFLANG_EXCLUDE%"
if /i "%TUFLANG_EXCLUDE%"=="none" set "EXCLUDE_ARG="

if not exist "%TUFLANG_DIR%\.git" (
	echo Cloning tuf-languages into %TUFLANG_DIR% ...
	git clone -q https://github.com/schultz-it-solutions/tuf-languages.git "%TUFLANG_DIR%" || exit /b 1
)
pushd "%TUFLANG_DIR%" || exit /b 1

git checkout -q main || goto :fail
git pull -q --ff-only || goto :fail

"%PHP%" tools\source-import.php --version=%TUFLANG_VERSION% --root="%TUF_ED_ROOT%" --check
if errorlevel 3 (
	if "%TUFLANG_STRICT%"=="1" (
		echo ERROR: your local en-GB files differ from the repository. Run lang-upload.bat and merge the pull request first.
		goto :fail
	)
	echo WARNING: your local en-GB files differ from the repository ^(see above^). New or changed strings
	echo          are not in the translations yet. Run lang-upload.bat and merge the pull request.
) else if errorlevel 1 goto :fail

"%PHP%" tools\build.php --version=%TUFLANG_VERSION% %EXCLUDE_ARG% --into="%TUFLANG_PKG%" || goto :fail

popd
exit /b 0

:fail
echo ERROR: lang-download.bat stopped, see the message above.
popd
exit /b 1
