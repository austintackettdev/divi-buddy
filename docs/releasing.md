# Releasing Divi Buddy & site updates

Sites update from **GitHub Releases** of `austintackettdev/divi-buddy`. Each release has a
`divi-buddy.zip` attached. WordPress shows the update on the Plugins screen like any other plugin.

## Publishing a new version

1. Bump the version in **both** places in `divi-buddy.php` (a unit test fails if they differ):
   - the header line `Version: x.y.z`
   - `define( 'DBUDDY_VERSION', 'x.y.z' );`
2. Commit and push to `main`.
3. Tag and push the tag:
   ```
   git tag v0.2.0
   git push origin v0.2.0
   ```
4. GitHub Actions (`.github/workflows/release.yml`) then does the rest automatically:
   - runs the unit tests,
   - checks the tag matches both version strings,
   - builds `divi-buddy.zip` with `git archive` (files marked `export-ignore` in `.gitattributes`
     are left out: tests, docs, CLAUDE.md, Composer files),
   - publishes the GitHub Release with the zip attached.
5. Sites see the update within about 12 hours. To check sooner, use the plugin's
   **"Check for updates"** link on the Plugins screen.

If the workflow fails, nothing is published. Fix the problem, delete the tag
(`git tag -d v0.2.0 && git push origin :refs/tags/v0.2.0`), and tag again.

Version numbers: `x.y.z`. Bump `z` for fixes, `y` for new features, `x` for big changes.

## Installing on a new site

1. Download `divi-buddy.zip` from the latest release on GitHub.
2. In WordPress: **Plugins → Add New Plugin → Upload Plugin**, choose the zip, then **Install** and **Activate**.
3. Optional: on the Plugins screen, click **Enable auto-updates** for Divi Buddy so the site installs new versions on its own.

The repo is public, so sites need nothing else to receive updates.

## Access token (only if the repo is ever made private again)

A private repo's releases can only be seen with a token, so each site would need one. Skip this section while the repo is public.

### Create the token (once)

1. On GitHub, open your profile picture → **Settings → Developer settings → Personal access tokens → Fine-grained tokens → Generate new token**.
2. **Token name:** `Divi Buddy updates`
3. **Expiration:** the longest option available. Add a reminder to your calendar to replace it before it expires.
4. **Repository access:** **Only select repositories** → `divi-buddy`.
5. **Permissions → Repository permissions → Contents:** **Read-only**. (Metadata: Read-only is added automatically.) Leave everything else as "No access".
6. Click **Generate token** and copy it. GitHub only shows it once. Keep it in your password manager.

This token can only *read* this one repo. It can't change anything, and it can't see your other repos.

### Add it to each site

In the site's `wp-config.php`, above the line `/* That's all, stop editing! */`, add:

```php
define( 'DBUDDY_GITHUB_TOKEN', 'github_pat_xxxxxxxxxxxxxxxx' );
```

- Never commit the token to git or paste it anywhere public.
- When the token is replaced, update this line on every site.
- If the repo goes public again, remove the line from every site. Updates keep working without it.

## The development site never self-updates

When the plugin runs from a git checkout (a `.git` folder is present, as on a local dev site
where the plugin folder links to the project), the updater switches itself off. Updating there
would make WordPress delete the old plugin folder, which would wipe the project through the link.
To test updates, use a separate site with the plugin installed from a release zip.
