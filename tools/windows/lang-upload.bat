@echo off
rem ---------------------------------------------------------------------------
rem lang-upload.bat - upload the English (en-GB) language files of TUF_ED from the
rem local source tree to github.com/schultz-it-solutions/tuf-languages.
rem
rem Copies every en-GB .ini file of the TUF_ED extensions into the repository,
rem runs sync and validate, and pushes the result as a new branch with a pull
rem request (CI checks it; merge it on GitHub). Does nothing if en-GB is unchanged.
rem
rem Environment:
rem   TUF_ED_ROOT      (required) your TUF_ED source folder
rem   TUFLANG_DIR      local clone of tuf-languages (default: %TUF_ED_ROOT%\..\tuf-languages,
rem                    cloned on first use)
rem   TUFLANG_VERSION  version line in the repository (default: v0)
rem   PHP              PHP command line binary (default: php)
rem Needs Git for Windows (git on PATH, signed in to GitHub through the Git Credential
rem Manager). With the GitHub CLI (gh) the pull request is opened automatically.
rem Exit code 0 = done or nothing to do, 1 = error.
rem ---------------------------------------------------------------------------
setlocal EnableExtensions
if not defined TUF_ED_ROOT (
	echo ERROR: set TUF_ED_ROOT to your TUF_ED source folder.
	exit /b 1
)
if not defined TUFLANG_DIR set "TUFLANG_DIR=%TUF_ED_ROOT%\..\tuf-languages"
if not defined TUFLANG_VERSION set "TUFLANG_VERSION=v0"
if not defined PHP set "PHP=php"

if not exist "%TUFLANG_DIR%\.git" (
	echo Cloning tuf-languages into %TUFLANG_DIR% ...
	git clone -q https://github.com/schultz-it-solutions/tuf-languages.git "%TUFLANG_DIR%" || exit /b 1
)
pushd "%TUFLANG_DIR%" || exit /b 1

git checkout -q main || goto :fail
git pull -q --ff-only || goto :fail

"%PHP%" tools\source-import.php --version=%TUFLANG_VERSION% --root="%TUF_ED_ROOT%" || goto :fail

git status --porcelain -- %TUFLANG_VERSION%/source > "%TEMP%\tuflang-status.txt"
for %%F in ("%TEMP%\tuflang-status.txt") do if %%~zF==0 (
	echo The English source in the repository is up to date. Nothing to upload.
	del "%TEMP%\tuflang-status.txt"
	popd
	exit /b 0
)
del "%TEMP%\tuflang-status.txt"

"%PHP%" tools\sync.php --version=%TUFLANG_VERSION% || goto :fail
"%PHP%" tools\validate.php --version=%TUFLANG_VERSION% || goto :fail

for /f "usebackq delims=" %%S in (`call "%PHP%" -r "echo date('Ymd-His');"`) do set "STAMP=%%S"
set "BRANCH=source/%TUFLANG_VERSION%-%STAMP%"
git checkout -q -b "%BRANCH%" || goto :fail
git add -A -- %TUFLANG_VERSION% i18n || goto :fail
git commit -q -m "English source update %TUFLANG_VERSION% (%STAMP%)" || goto :fail
git push -q -u origin "%BRANCH%" || goto :fail

where gh >nul 2>nul
if errorlevel 1 (
	echo Pushed branch %BRANCH%. Open the pull request here:
	echo   https://github.com/schultz-it-solutions/tuf-languages/compare/main...%BRANCH%
) else (
	gh pr create --repo schultz-it-solutions/tuf-languages --base main --head "%BRANCH%" --title "English source update %TUFLANG_VERSION% (%STAMP%)" --body "Uploaded by lang-upload.bat from the local TUF_ED sources."
)

git checkout -q main
popd
exit /b 0

:fail
echo ERROR: lang-upload.bat stopped, see the message above.
git checkout -q main >nul 2>nul
popd
exit /b 1
