# Divi Buddy — CLAUDE.md

Divi Buddy is a WordPress plugin for Divi 5 sites. It gives site owners one settings page for site-wide colors, fonts, sizes and spacing, pushes those into Divi 5's global colors and variables, and (planned) ships a shared library of ready-made sections.

It is built for the author's own client sites. It isn't sold, so there's no licensing or paywall. It's an original implementation: don't copy code, branding, names, assets or UI text from other products.

Machine-specific and personal notes live in `CLAUDE.local.md`, which is not committed. Never put personal details (names, local paths, client info) in committed files.

## Scope

1. **Global Design Settings page** where non-technical clients set site-wide design values:
   - **Colors:** Divi's 5 built-in colors plus accent, light/dark backgrounds and border.
   - **Typography:** heading and body fonts, weights, line heights, and a font-size scale (H1–H6, body, small). Fluid sizes use `clamp()`.
   - **Spacing:** a spacing scale (xs → xxl), section padding and the gap between columns.
   - **Shapes:** corner rounding and content width.
2. **Push those settings into Divi 5** (global colors, built-in fonts, global variables) so they appear in the Visual Builder.
3. **Section library** (built): sections the author builds in Divi 5 ship with the plugin and appear on every site as native, editable Divi 5 content in the Divi Library.
4. **Self-updates from GitHub Releases** (built).

### Out of scope

- **No AI features of any kind:** no content generation, no API keys, no calls to LLMs.
- No page or site generation, no wizards, no intake questionnaires. Pages are built in Divi as usual.
- No Divi 4 support. No other builders or themes.
- No licensing, paywall or telemetry.

## Users

- **Clients** are the primary UI users: non-coders editing their own sites with Administrator accounts. Only Administrators can see or use Divi Buddy. Every screen must work without any CSS or Divi knowledge:
  - Plain-language labels with a short help text under each field. Avoid jargon such as "token", "variable" or "clamp" in help text.
  - **Labels match the names shown in the Divi Builder** (e.g. "Primary Color"). A field's `label` is also the name sent to Divi, with no prefix, so the settings page and the builder always agree.
  - Color pickers, font dropdowns and visual previews. Never ask for raw CSS.
  - "Reset to defaults" per tab and for the whole page, with confirmation.
  - Validate input safely. A bad value must never break the site's front end.
  - A clear success or error notice after every save.

## Tech stack & constraints

- **PHP 8.1+, WordPress 6.5+, Divi 5 required.** If Divi 5 isn't active, show an admin notice and do nothing else (the updater still runs). Never fatal-error.
- **Plain PHP + vanilla JS + CSS, no build step.** This may change later, so keep the admin JS modular.
- Use WP core UI: the Settings API, `wp-color-picker`, standard admin markup.
- Composer is for **dev dependencies only** (PHPUnit, Brain Monkey). Runtime libraries are committed under `lib/`, because sites must not need Composer.

## Naming conventions

| Thing | Value |
|---|---|
| Plugin name | Divi Buddy |
| Slug / folder / text domain | `divi-buddy` |
| Main file | `divi-buddy.php` |
| PHP namespace | `DiviBuddy\` (PSR-4 → `includes/`) |
| Function/option/hook prefix | `dbuddy_` |
| Constants | `DBUDDY_` (e.g. `DBUDDY_VERSION`, `DBUDDY_PATH`, `DBUDDY_URL`) |
| CSS custom properties | `--dbuddy-*` |
| CSS classes / JS globals | `dbuddy-` / `DiviBuddy` |
| Divi IDs | `gcid-dbuddy-*` (colors), `gvid-dbuddy-*` (variables) |
| Capability | `manage_divi_buddy`, granted **only to Administrators**. On multisite, super admins pass too. |

All user-facing strings are translatable with the `divi-buddy` text domain.

## Structure

```
divi-buddy/
├── divi-buddy.php              # Header, constants, autoloader, bootstrap
├── uninstall.php               # Removes Divi Buddy's own options (leaves Divi data in place)
├── includes/
│   ├── Plugin.php              # Wires up modules; boots on `init` priority 5 (Divi 5 boots at 0)
│   ├── Updater.php             # GitHub self-update
│   ├── Admin/SettingsPage.php
│   ├── Settings/Schema.php     # Single source of truth for every setting
│   ├── Settings/Sanitizer.php  # Pure, unit tested
│   ├── Settings/Settings.php   # Reads/writes the `dbuddy_settings` option
│   ├── Divi/Divi.php           # Divi detection and helpers
│   ├── Divi/PayloadBuilder.php # Settings → Divi data (pure, unit tested)
│   ├── Divi/VariablesSync.php  # Writes to Divi through its GlobalData API
│   ├── Frontend/CssBuilder.php # Settings → CSS (pure, unit tested)
│   ├── Frontend/CssOutput.php
│   ├── Library/                # BlockMarkup, SectionConverter, Library (pure) + LibrarySync (WP)
│   └── Support/                # FluidSize (clamp math), ValueFormatter
├── assets/admin/{css,js}/
├── assets/library/placeholder.svg
├── bin/library-add.php         # Export → library converter (dev only, not shipped)
├── lib/plugin-update-checker/  # Bundled third-party library (MIT), don't edit
├── library/                    # Portable section files (<id>.json), shipped with the plugin
├── docs/                       # divi5-notes.md, releasing.md, library.md
└── tests/unit/                 # PHPUnit + Brain Monkey
```

## Architecture rules

- **The settings schema drives everything.** `Settings/Schema.php` defines each setting once: type, label, help text, default, CSS property and Divi target. The form, sanitizing, defaults, Divi sync and CSS output are all generated from it. Adding a global should only need a schema entry.
- All settings live in **one option**, `dbuddy_settings`: `[ 'schema_version' => n, 'values' => [...] ]`.
- **Divi integration is isolated** in `includes/Divi/`. Never overwrite Divi data the plugin didn't create. Keep the one-time backup of Divi's data from before the first sync (`dbuddy_divi_backup`). If Divi's API is missing, skip the sync and show a notice.
- **Always output `--dbuddy-*` CSS custom properties** on `:root` (`Frontend/CssBuilder`), so custom CSS can use them even if the Divi sync breaks. CssBuilder also applies text sizes, weights and line heights site-wide with `body h1`-style rules (specificity 0,0,2: beats Divi's theme defaults, loses to per-module builder settings). The `dbuddy_apply_site_styles` filter turns that off.
- **Sync triggers:** saving settings (`add_option_`/`update_option_dbuddy_settings`), the "Send settings to Divi again" button, and a pending flag set on activation or version change that runs on the next admin page load. Every sync reads its values back from Divi and records the result in `dbuddy_sync_status`.
- **Defaults before the first save** come from Divi's current built-in colors and fonts, so activating the plugin never changes how a site looks.
- Admin assets load only on the plugin's own screen.
- Security on every request: check capabilities, verify nonces, sanitize input, escape output.

### Divi 5 internals: see `docs/divi5-notes.md`

The research findings (verified on Divi 5.19.0) are in [docs/divi5-notes.md](docs/divi5-notes.md). Read it before touching any Divi integration, and keep it updated. Key rules:
- Write through Divi's public API, `\ET\Builder\Packages\GlobalData\GlobalData`, never raw `update_option`:
  - `import_global_variables()` to merge variables.
  - `sanitize_global_colors_data()` + `set_global_colors( $data, true )` to merge colors.
  - The 5 built-in colors and 2 built-in fonts are Divi theme options. Divi Buddy maps its matching settings onto them instead of duplicating them.
- Divi's variable save requires a user with `edit_theme_options`, so syncs run during admin requests, not in cron.
- Anything marked ❓ in the notes must be verified before it's relied on.

## Section library (built)

Author workflow: [docs/library.md](docs/library.md). Code: `includes/Library/`.
- **Converter** (`bin/library-add.php`, run as `composer library:add`): Divi Library export `.json` files (from the git-ignored `library-inbox/`) → `library/<id>.json` (`{ id, title, divi_version, content }`). The id is the slugified title. `SectionConverter` enforces portability:
  - image URLs → `{{dbuddy:placeholder-image}}` token, with sibling `id`/`attachmentId` dropped,
  - `modulePreset`/`groupPreset` → error,
  - colors/variables other than `gcid-dbuddy-*`, `gvid-dbuddy-*`, Divi's 5 built-in colors and 2 built-in fonts → error,
  - other absolute links → warning.

  It's pure PHP with no WordPress (`BlockMarkup` parses and re-encodes block attribute JSON exactly like WordPress's `serialize_block_attributes`). Untouched blocks stay byte-identical.
- **Sync** (`LibrarySync`): runs on the next admin page load whenever the library folder's fingerprint (file names, sizes and mtimes, plugin version, placeholder URL) changes, plus the "Update section library" button. It writes `et_pb_layout` posts with the terms and meta from the notes, the `Divi Buddy` `layout_category`, and `_dbuddy_library_id`. The token resolves to `assets/library/placeholder.svg` on each site.
  - Edits made on the site are detected with `_dbuddy_local_hash` (a hash of the title and content read back after writing) and kept. "Restore original" forces a rewrite.
  - Items trashed by a client stay trashed. Items Divi Buddy trashed itself (section removed from the plugin, marked `_dbuddy_removed`) come back when the section returns.
  - kses filters are lifted while writing, and the content is passed through `wp_slash()`.
- Divi lists library items live from the database (`ET_Builder_Library::builder_library_layouts_data()`, key `layouts_data.items`), so no cache clearing is needed.

## Nice-to-haves (after v1, confirm first)

- Export/import settings as JSON, to copy a design setup between sites.
- Owner "lock" for specific settings so clients can't change them.
- Live preview panel on the settings page.
- A "Divi Buddy" folder for its colors and variables in Divi's variable manager (the owner chose to wait on this).

## Releases & self-updates

- Built. See `includes/Updater.php` and [docs/releasing.md](docs/releasing.md) for the release steps and installing on a new site.
- Uses **[Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker)** v5.7 (MIT), committed in `lib/plugin-update-checker/`. Don't edit it. To upgrade it, replace the folder and check `Updater.php` (it reads a constant from the version-specific `v5p7` namespace).
- Sites install only the `divi-buddy.zip` asset of a GitHub Release. Pushing a `vX.Y.Z` tag runs `.github/workflows/release.yml`, which runs the tests, checks the tag matches both version strings, builds the zip with `git archive` (dev files are `export-ignore`d) and publishes the release.
- The repo is public, so no token is needed. `DBUDDY_GITHUB_TOKEN` is still supported if the repo ever goes private again. Never hardcode or commit a token.
- **The updater is disabled when `.git` exists in the plugin folder**, because updating a linked dev copy would delete the working tree.

## Testing

- **Unit tests:** PHPUnit + Brain Monkey (WP functions are mocked; no WordPress needed). Cover the schema, sanitizers, clamp math, CSS generation and Divi payloads. Keep Divi-touching logic as pure functions so it can be tested.
- **Integration/E2E (later):** a real WP + Divi site, possibly Playwright for the settings page.
- Commands: `composer test` (or `php vendor/bin/phpunit`). PHPCS isn't set up yet.
- Write or update tests with every feature.

## Working agreements for Claude

- Keep v1 small: global settings, Divi sync, CSS fallback, section library and updater. Don't add features outside this list without asking.
- Never add AI features.
- For anything that depends on Divi 5 internals, verify it instead of assuming, and say clearly what was verified.
- Prefer WordPress core APIs and patterns over custom solutions.
- Commit only when asked. The default branch is `main`.
