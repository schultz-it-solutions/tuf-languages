# TUF_ED language packs

Translations for [TUF_ED](https://it-solutions.schultz.ch) – *Secure updates for Joomla
extensions*. TUF_ED lets Joomla extension developers sign their updates with
[The Update Framework](https://theupdateframework.io). All translations are shipped inside
the TUF_ED package; on installation Joomla adds only the languages the site has installed.

## Status

Each language is a mix of translations by volunteers (**reviewed**) and translations made
by an AI agent that no native speaker has checked yet (**machine**). Run
`php tools/diff.php --version=v0 --summary` for the full table.

TUF_ED is security software, so a few strings (security warnings, the key-management
statement) are **shown in English until a native speaker has reviewed them**. That way a
machine translation can never weaken a warning.

**Help is very welcome**: if you speak one of the languages, reviewing machine
translations is the most valuable contribution, and it takes no technical setup.

## How to help

### Review or correct a translation (no tools needed)

1. Open the file of your language, e.g. `v0/translations/de-DE/admin/com_tuf_ed.ini`,
   and use GitHub's edit button (pencil icon). GitHub creates a fork and a pull request
   for you.
2. Change only the text between the quotes. Keep the key on the left, all HTML tags,
   links and placeholders such as `%s` exactly as they are. Keep the TUF terms *root*,
   *targets*, *snapshot* and *timestamp* and the role names (TUF_ED, TUF_IN, TUF_EDC) in
   English.
3. In the pull request description, say whether you changed individual strings or
   reviewed the whole file / language.

An automatic check validates every pull request. Your corrections are then marked as
reviewed and terms you chose go into the language's glossary in
[`i18n/glossary/`](i18n/glossary/), so future translations follow them. You can also
correct the glossary directly; that is often the quickest way to fix a recurring term.

### Report a problem

Open an issue (template "Translation problem") with the language, the key and what is
wrong.

### Request a new language

Open an issue (template "Request a language"). New languages start machine-translated
and become "reviewed" as native speakers check them.

## Repository layout

| Path | Content |
|---|---|
| `v0/` | One folder per TUF_ED version line (`v0` = all 0.x releases) |
| `<version>/source/en-GB/admin/` | English master texts of com_tuf_ed, plg_system_tufed, lib_com_tuf, pkg_tuf_ed |
| `<version>/translations/<tag>/` | One folder per language |
| `i18n/languages.json` | Languages, package versions, review-required strings |
| `i18n/state/` | Translation status per string (maintained by the tools) |
| `i18n/glossary/` | Term decisions per language |
| `tools/` | PHP command-line tools (diff, apply, sync, validate, review, build) |

Untranslated strings carry the English text, so an incomplete language pack never shows
raw language keys.

## For maintainers

```
php tools/diff.php     --version=v0 --summary                     # status table
php tools/validate.php                                            # checks (also run by CI)
php tools/sync.php     --version=v0                               # after updating source/en-GB
php tools/review.php   --version=v0 --lang=de-DE --base=<ref>     # record a volunteer review
php tools/build.php    --version=v0                               # files for pkg_tuf_ed into dist/v0/embed/
```

Translation work by the agent follows
[`.claude/skills/translate-tuf/SKILL.md`](.claude/skills/translate-tuf/SKILL.md).

## License

GNU General Public License v3, see [LICENSE](LICENSE).
