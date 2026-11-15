# tuf-languages

Translations of TUF_ED (com_tuf_ed with plg_system_tufed, lib_com_tuf and pkg_tuf_ed),
shipped inside pkg_tuf_ed (`tools/build.php` produces a `language/` folder and the
`<languages>` block for the package manifest). Later also TUF_IN.

- `v0/`: one folder per version line (`v0` covers all 0.x releases). `source/en-GB/` is
  the English master copied from the extensions; `translations/<tag>/` mirror it.
- All language files live in `admin/` (component, plugin, library and package files
  are all installed in the administrator language folder). `site/` only if the
  component gets frontend strings.
- `i18n/languages.json`: per version the product, package slug, package version (only
  used by `build.php --format=packs`),
  languages and `reviewRequired` key patterns (security strings shipped in English
  until reviewed by a native speaker).
- `i18n/state/<version>.json`: per string the hash of the English text it was
  translated from and its status (`reviewed`, `machine`, `needs-fix`).
- `i18n/glossary/`: `_shared.md` for all languages, `_template.md` for new languages,
  plus one file per language.
- `tools/`: plain PHP 8.1+ CLI scripts, no dependencies.

For any translation work use the `translate-tuf` skill
(`.claude/skills/translate-tuf/SKILL.md`). Never hand-edit `.ini` files under
`translations/` or the state file; use the tools. Run `php tools/validate.php` before
committing; CI runs it on every pull request.
