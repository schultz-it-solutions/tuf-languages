---
name: translate-tuf
description: Translate, update or fix TUF_ED language files in this repository (missing strings, changed English texts), sync a new English source from a component release, or add a language. Use for any request like "translate de-DE", "update the translations", "add Polish", "new 0.2.0 strings".
---

# Translating TUF_ED

The tools in `tools/` own the `.ini` files and `i18n/state/*.json`. **Never edit
translation files or state by hand**: always go through `diff.php` → `apply.php`, so
every string is checked and tracked. Never change `*/source/en-GB/` except when syncing a
new component release (workflow B).

TUF_ED is security software. Read the introduction of `i18n/glossary/_shared.md` before
every session: a translation must never make a warning weaker or more optional than the
English.

## A. Translate one language

1. Overview: `php tools/diff.php --version=v0 --summary`
2. Read `i18n/glossary/_shared.md` and `i18n/glossary/<tag>.md` completely. The language
   glossary wins over everything else. If it has no style decisions yet (form of
   address, tone), decide following the official Joomla translation for that language
   and write the decision into its "Notes" section, marked "(agent decision, please
   review)".
3. Get a batch: `php tools/diff.php --version=v0 --lang=<tag> --limit=150 > work/<tag>-batch.json`
4. For every item write a `value` and save the items as `work/<tag>-out.json`
   (a JSON array of `{file, key, value}`; you may keep the other fields).
   - **missing**: translate `en`.
   - **changed**: the English text changed; `current` is the old translation. Adapt it
     to the new English, keeping its wording where the meaning is unchanged.
   - **needs-fix**: a translation fails a check (`problems`). Fix only what the problem
     names; keep the wording.
   - **tm** present: the identical English text is already translated elsewhere. Reuse
     it unless the key context clearly needs something different.
   - **reviewRequired**: a security-relevant string. Translate it with extra care and
     list its key in the PR; it is shipped in English until a native speaker reviews it.
   - **Context**: look at the key name and its neighbours in
     `<version>/source/en-GB/<file>`: `_LABEL` is a short form label, `_DESC` its
     tooltip, `_OPTn` the choices of a dropdown, `*_XML_DESCRIPTION` is HTML shown in the
     extension manager, `PLG_SYSTEM_TUFED_*` belong to the system plugin, `LIB_COM_TUF_*`
     to the shared library.
5. Apply: `php tools/apply.php --version=v0 --lang=<tag> --input=work/<tag>-out.json`
   - Agent translations use the default status `machine`.
   - Exit code 1 lists REJECTED items: fix exactly those and apply again.
6. Repeat 3–5 until `remaining` is 0.
7. Add every new term you decided on to the language glossary table.
8. `php tools/validate.php --version=v0 --lang=<tag>` must report 0 errors.
9. Commit on a branch `translate/<version>-<tag>` and open a PR titled
   `<tag>: <n> strings translated (<version>)`. In the description list: counts by
   reason, glossary terms added or decided, the review-required keys translated, and
   **English source problems** you noticed (typos, broken HTML, unclear strings, strings
   concatenated in code) so the maintainer can fix them in the component. Ask for review
   by a native speaker.

Work in batches; do not load an entire language at once. Keep `work/` out of commits
(it is git-ignored).

## B. New English source from a component release

1. Usually the maintainer uploads them with `tools/windows/lang-upload.bat`
   (`tools/source-import.php`), which arrives as a pull request "English source update";
   then only steps 3 and 4 remain. Otherwise copy the `en-GB` files of all TUF_ED
   extensions into `<version>/source/en-GB/admin/`
   (plugin, library and package language files are installed in the administrator
   language folder too), using unprefixed names: `com_tuf_ed.ini`, `com_tuf_ed.sys.ini`,
   `plg_console_tufed.ini`, `plg_console_tufed.sys.ini`, `plg_system_tufed.ini`,
   `plg_system_tufed.sys.ini`, `plg_task_tufed.ini`, `plg_task_tufed.sys.ini`, `lib_com_tuf.sys.ini`,
   `pkg_tuf_ed.sys.ini`. Only if the component has frontend strings, they go to `site/`.
   Convert line endings to LF.
2. `php tools/sync.php --version=<version>`. New keys appear with English text, removed
   keys disappear, and keys whose English text is identical to an existing translation
   are carried over automatically (this preserves work when keys are renamed or moved).
3. `php tools/diff.php --version=<version> --summary` shows what is left; continue with
   workflow A per language.
4. If the release adds security warnings or statements, add their key patterns to
   `reviewRequired` of the version in `i18n/languages.json`.

## C. Add a language

1. Add the tag to `versions.<version>.languages` and to `names` in `i18n/languages.json`
   (Joomla tags: `pl-PL`, `pt-BR`, `zh-TW`, `sr-YU` (Latin) / `sr-RS` (Cyrillic) ...).
2. `php tools/sync.php --version=<version>` creates the files (English placeholders).
3. Copy `i18n/glossary/_template.md` to `i18n/glossary/<tag>.md` and fill in the header.
4. Translate with workflow A.

## D. A volunteer reviewed a language

After merging their PR: `php tools/review.php --version=<v> --lang=<tag> --base=<commit before the PR>`
(only their edits become reviewed) or `--approve-all` (they approved the whole language).
Then add any term decisions from their review to the glossary.

## Build packages

`php tools/build.php --version=v0` → `dist/v0/embed/` with `language/<tag>/*.ini` for
all languages and `languages.xml`, the `<languages>` block for `pkg_tuf_ed.xml`. The
maintainer copies `language/` next to `pkg_tuf_ed.xml` in the TUF_ED build and replaces
the manifest's `<languages>` block. Joomla installs only languages the site already has.
Review-required strings that are not reviewed yet are written in English.

`--format=packs` builds separate installable language packs instead (not used at the
moment).
