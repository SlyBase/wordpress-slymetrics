---
applyTo: '**'
description: 'Changelog, readme and version management rules for every code change'
---

# Changelog & Version Management

## 1) Canonical changelog files

After **every** code change, update both changelog files:

- `CHANGELOG.md` (repository root) is the canonical changelog in Markdown.
- `slytranslate/changelog.txt` is the plain-text mirror (text content only; no Markdown headings/tables/links).

### Version block rules

Always add entries directly under the current version block in **both** files. Never use an `Unreleased` block. If the version block does not exist yet, create it at the top.

If the version includes a beta suffix (for example `1.6.0-beta.1`), always use the base version without suffix (that is, `1.6.0`) in both changelog files.

### Categories

Group entries within a version block by category. Use exactly these three categories (omit a category if it has no entries):

`CHANGELOG.md`:

```md
## [X.Y.Z]
### Features
- <New capability or addition>

### Changes
- <Behavioral or non-breaking change>

### Fixes
- <Bug fix>
```

`slytranslate/changelog.txt`:

```txt
= X.Y.Z =
Features:
* <New capability or addition>

Changes:
* <Behavioral or non-breaking change>

Fixes:
* <Bug fix>
```

### Keep entries concise

Each entry should be one short sentence. Strip implementation details, internal class/method names, and test coverage notes — only document what matters to a user or integrator.

## 2) Release workflow source for GitHub releases

- GitHub release/prerelease notes are generated from `CHANGELOG.md`.
- Extraction always targets the base version `X.Y.Z`.
- For prerelease tags like `X.Y.Z-beta.N`, `X.Y.Z-alpha.N`, or `X.Y.Z-rc.N`, use the `X.Y.Z` section from `CHANGELOG.md`.
- This ensures prereleases display the complete text from the base version section.

## 3) Keep readme.txt and README.md in sync

For **major feature changes or additions** (for example new/changed/removed abilities or other relevant behavior changes), both files must be updated:

- `slytranslate/readme.txt` - update sections `== Description ==`, `== Frequently Asked Questions ==`, and the abilities list.
- `README.md` (root) - mirror the same content in the corresponding sections.

The shared documentation sections must be identical.
Changelog entries are maintained in `CHANGELOG.md` and `slytranslate/changelog.txt`.

## 4) readme.txt in WordPress.org format (important for LLMs)

`readme.txt` in the Plugin Directory follows its own parser-driven format and **not** the usual GitHub README style.
When an LLM generates content for `slytranslate/readme.txt`, it must follow these rules:

- **Strictly follow filename and heading syntax:**
	- Title line: `=== Plugin Name ===`
	- Top-level sections: `== Section ==`
	- Subsections/FAQ questions/version entries: `= Subsection =`
	- No ATX headings (`#`, `##`) and no YAML frontmatter.
- **Write header fields in the expected structure** (one field per line):
	- `Contributors:` (WordPress.org usernames, comma-separated)
	- `Donate link:` (optional)
	- `Tags:` (1 to 5 tags)
	- `Tested up to:` (numeric only, for example `6.8`)
	- `Stable tag:` (plugin version, not WordPress version)
	- `License:`
	- `License URI:` (optional)
- **Place the short description directly below the header:**
	- Maximum about 150 characters
	- No markup in this line
- **Use core sections in WordPress format:**
	- `== Description ==`
	- `== Installation ==` (if relevant)
	- `== Frequently Asked Questions ==`
	- `== Screenshots ==` (if present)
	- `== Changelog ==`
	- `== Upgrade Notice ==` (recommended for important upgrades)
- **Structure Changelog and Upgrade Notice correctly:**
	- Versions as `= X.Y.Z =`
	- Newest version at the top
	- Keep each upgrade note short (guideline: max. 300 characters)
- **Reference screenshots correctly:**
	- Numbered list (`1.`, `2.`, ...)
	- Files follow the pattern `screenshot-1.png`, etc., in the `/assets` directory
- **Understand Stable Tag parsing:**
	- The Plugin Directory first reads `Stable tag` from `/trunk/readme.txt`
	- It then uses `readme.txt` from `/tags/<Stable tag>/` for display
	- Avoid `Stable tag: trunk` (not recommended)
	- The displayed download version comes from the plugin header in `ai-translate.php`, not from `readme.txt`
- **WordPress 5.8+ detail:**
	- `Requires at least` and `Requires PHP` are read from the main PHP file, not from `readme.txt`
- **Keep the file concise:**
	- Avoid very large readmes (guideline: >10 KB)
	- Move long historical changelogs to a separate file when needed

References for format and parser behavior:
- Example/standard: https://wordpress.org/plugins/readme.txt
- Parsing rules: https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/
- Validator: https://wordpress.org/plugins/developers/readme-validator/

Minimal template for LLM outputs:

```txt
=== Plugin Name ===
Contributors: username1, username2
Donate link: https://example.com/
Tags: tag1, tag2
Tested up to: 6.8
Stable tag: 1.6.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Short description without markup (max. about 150 characters).

== Description ==
Long description using a simple Markdown subset.

== Installation ==
Installation steps.

== Frequently Asked Questions ==
= Question =
Answer.

== Screenshots ==
1. Description for screenshot-1.png

== Changelog ==
= 1.6.0 =
* Change

== Upgrade Notice ==
= 1.6.0 =
Short upgrade note.
```

## 5) Set the version number

A version number may only be incremented when code has actually changed.
For documentation-only, prompt-only, instruction-only, or other non-code changes, **do not** bump the version.

If a code change explicitly specifies a version number (for example `1.4.0`), **all three** locations below must be updated in sync:

For beta versions (`X.Y.Z-beta.N`), use the base version `X.Y.Z` for this synchronization as well.

| File | Field |
|---|---|
| `slytranslate/readme.txt` | `Stable tag: X.Y.Z` |
| `slytranslate/ai-translate.php` | Plugin header `Version: X.Y.Z` |
| `slytranslate/ai-translate.php` | Class constant `private const VERSION = 'X.Y.Z';` |