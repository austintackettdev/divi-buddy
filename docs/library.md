# Section library: adding and updating sections

Divi Buddy ships a library of ready-made sections. On every site they appear in the Divi Library
under the **Divi Buddy** category, so clients can add them to any page from the Divi Builder.

## Adding a section

1. **Build it in Divi 5** on your dev site (the one with Divi Buddy installed). Use only:
   - **Divi Buddy colors and variables** (e.g. Accent Color, Section title (H2) size, Large space) and
     Divi's 5 built-in colors (Primary, Secondary, Heading Text, Body Text, Link). These exist on
     every site, so the section adapts to each site's own settings.
   - **No presets.** Preset IDs only exist on the site they were made on.
   - Images are fine: they're swapped for a neutral placeholder automatically.
2. **Save it to the Divi Library** as a section. Its name is what clients will see.
3. **Export it:** Divi → Divi Library → select the section → Export. You get a `.json` file.
4. **Put the file in the project's `library-inbox/` folder.** This folder is ignored by git.
5. **Convert it:** run `composer library:add`, or ask Claude to. For each section it:
   - swaps images for the placeholder and drops media-library IDs,
   - **rejects** the section if it uses a preset or a color/variable that only exists on your dev site,
   - **warns** about links (e.g. a button linking to your dev site's contact page),
   - writes `library/<section-name>.json`.
6. **Check it** on the dev site: open Divi Buddy. The section syncs into the Divi Library automatically,
   and the **Section Library** list shows it as "Ready to use".
7. **Commit and release** (see [releasing.md](releasing.md)). Sites pick it up after they update.

## Updating a section

Change it in Divi, export it again under the **same name**, and convert it again. The library file is
replaced (the name decides the file: "Hero Split" → `library/hero-split.json`). After the release:

- Sites where nobody touched that library item get the new version automatically.
- Sites where someone edited the item in their Divi Library keep their edit. The Divi Buddy page shows
  "Changed on this site" with a **Restore original** button.
- Pages that already use the section are never changed. Each page has its own copy.

Renaming a section creates a new library item. The old name is treated as removed (see below).

## Removing a section

Delete its file from `library/` and release. On each site the library item moves to the trash,
unless someone edited it there, in which case it's left alone. Pages that use it are unaffected.
If the section is added back later, it returns automatically.

If a **client** deletes a Divi Buddy item from their library, it stays deleted. They can bring it back
with **Restore original** on the Divi Buddy page.
