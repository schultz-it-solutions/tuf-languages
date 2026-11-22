# Shared glossary and rules (all languages)

## What TUF_ED is

TUF_ED is a Joomla component (com_tuf_ed, with plg_system_tufed and the library
lib_com_tuf) that lets Joomla extension developers sign the update information of
their extensions with [The Update Framework (TUF)](https://theupdateframework.io), so
Joomla sites can verify updates before they install them. It runs on a dedicated Joomla
instance whose frontend stays offline. All strings are read by the developer's own
team in the Joomla backend: owners, signers and administrators of that instance, not
by website visitors.

Many strings are **security warnings and instructions** (keys, signing, roles,
an instance that is not clean). Accuracy beats elegance: a translation must never make
a warning sound weaker, more optional or more reassuring than the English. If a
sentence is ambiguous, keep the stricter reading and report the source string.

## Core concepts (translate consistently, see the language glossary)

| English | Meaning |
|---|---|
| Key | A cryptographic signing key (Ed25519). Translate as the usual IT term for a cryptographic key, never as a door key or a keyboard key |
| Private key / public key | As in public-key cryptography; use the established IT terms of the language |
| Sign / signature | Cryptographic signing, not a handwritten signature where the language distinguishes them |
| Metadata | The signed TUF files that describe the available updates |
| Key rotation | Replacing a key with a new one |
| Offline key | A key that is kept outside the server and only uploaded for a signing step |
| Transparency log | Append-only log of every signing event |
| Extension | A Joomla extension (component, module, plugin ...), as in the Joomla core translation |
| Update server | As in the Joomla core translation |
| Key-management statement | The owner's written statement of how the keys are stored and protected |

The language glossary (`<tag>.md`) always takes precedence over this file. Where it has
no entry and the Joomla core translation of the language has a term (extension, update,
user group, access level, scheduled task, super user), use the Joomla term, then add it
to the language glossary.

## Never translate

- Role and product names: TUF_ED, TUF_IN, TUF_EDC, TUF, The Update Framework, Joomla,
  JED, Schultz IT Solutions, php-tuf, libsodium, Ed25519
- The four TUF metadata roles **root, targets, snapshot, timestamp**: they are names
  from the TUF specification and appear as file names (`root.json` ...). Keep them in
  English, lower case as in the source; you may add a word of the target language
  around them ("the root key", "der root-Schlüssel")
- User group names created by TUF_ED, such as "TUF_ED Owner"
- Technical tokens: JSON, SFTP, CLI, URL, HTTPS, SHA-256, PHP, ID, `.well-known`,
  file and folder names, command lines and options (`php cli/joomla.php ...`, `--force`)
- Placeholders: `%s`, `%1$s`, `%d`, anything in `{...}`
- URLs, `href` targets, CSS class names, HTML tag names and attributes

## Format rules

- Keep every HTML tag; translate only the visible text between tags.
- Attribute quotes in HTML are single quotes (`href='...'`). Never use `"_QQ_"`. A literal
  double quote in text is written as `\"` (the tools do this).
- Keep leading/trailing spaces and punctuation that the English string has.
- Keep the length roughly similar for `_LABEL` strings (form labels and buttons).
- Imperatives in instructions stay imperatives ("Store the key offline"), do not turn
  them into suggestions ("you could store ...").
- If the English source looks wrong (typo, broken HTML, unclear), translate the intended
  meaning and report the source problem; do not copy the mistake.
