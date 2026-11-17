# Divi Buddy

A WordPress plugin for **Divi 5** sites. It gives site owners one simple settings page for
site-wide design: colors, fonts, text sizes, spacing and corner rounding. Those settings are
pushed straight into Divi 5's global colors and variables, so they show up in the Divi Builder
and can be used anywhere on the site.

- Plain-language settings page for non-technical site owners
- Syncs with Divi 5 global colors, fonts and variables (only touches its own items)
- Text sizes and spacing scale smoothly between phone and desktop sizes
- Self-updates from this repository's GitHub Releases

## Requirements

- WordPress 6.5+
- PHP 8.1+
- Divi 5 (theme)

## Installation

1. Download `divi-buddy.zip` from the [latest release](https://github.com/austintackettdev/divi-buddy/releases/latest).
2. In WordPress, go to **Plugins → Add New Plugin → Upload Plugin**, choose the zip, then install and activate it.
3. Open **Divi Buddy** in the admin menu.

Future versions appear on the Plugins screen like any other plugin update.

## Development

```
composer install
composer test
```

Releases are built automatically when a version tag is pushed. See [docs/releasing.md](docs/releasing.md).

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
