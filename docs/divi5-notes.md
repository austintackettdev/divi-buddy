# Divi 5 internals — research notes

Verified on **Divi 5.19.0**, WP 7.1.4, PHP 8.1, using the local site `divi-buddy-dev`. Sources:
- Divi's own source code, in `wp-content/themes/Divi/includes/builder-5/server/Packages/GlobalData/`.
- A read-only database dump made by a temporary inspection script.

Re-check these notes whenever Divi gets a major update. ✅ = verified in source or data. ❓ = still to verify.

## Public PHP API — use this, never write options directly ✅

Class: `\ET\Builder\Packages\GlobalData\GlobalData` (all methods are static).

| Need | Method | Notes |
|---|---|---|
| Read global colors | `get_global_colors(): array` | Merges the 5 "customizer" colors with the custom ones. |
| Write global colors | `set_global_colors( array $data, bool $already_sanitized = false )` | ⚠️ With `false`, it **replaces all** custom colors. With `true`, it **merges** (array_merge by ID), but the data must already be sanitized: call `sanitize_global_colors_data()` first. Clears Divi's static CSS cache automatically. |
| Read global variables | `get_global_variables(): array` | Returns `type => (object) [ id => item ]`. Includes the customizer fonts. |
| Write global variables (merge) | `import_global_variables( array $list, ?WP_Error &$err )` | **The one to use.** It takes a flat list of `['type','id','label','value','status']` and upserts onto the persisted data, skipping customizer fonts. It runs under Divi's lock and validation, and it clears the CSS cache. |
| Write global variables (raw) | `set_global_variables( $data, $expected )` | Replaces everything. It **rejects** a save if any existing `gvid-*` ID is missing. It silently returns `null` without saving if the user lacks `edit_theme_options` or the `variables_manager` role-editor permission. Avoid it. |

`import_global_variables()` goes through `set_global_variables()`, so the same **permission check applies**: it must run as a logged-in user with `edit_theme_options`, which Administrators have. It won't save during cron or WP-CLI unless a user is set first, e.g. with `wp_set_current_user()`.

## Storage (for reading, debugging and backups only) ✅

The theme shortname is `divi`, so options are prefixed `et_divi`. The Divi Builder *plugin* uses `et_divi_builder_plugin` instead.

| Data | Where |
|---|---|
| Custom global colors | `et_divi` option → `['et_global_data']['global_colors']` → `gcid-* => { color, label, folder, status, lastUpdated, usedInPosts }` |
| 5 "customizer" colors | `et_divi` option keys: `accent_color` (`gcid-primary-color`), `secondary_accent_color` (`gcid-secondary-color`), `header_color` (`gcid-heading-color`), `font_color` (`gcid-body-color`), `link_color` (`gcid-link-color`) |
| 2 "customizer" fonts | `et_divi` keys `heading_font` (`--et_global_heading_font`) and `body_font` (`--et_global_body_font`) |
| Global variables | Option `et_divi_global_variables`. It isn't created until the first variable is saved. Types: `numbers, strings, images, links, fonts, gradients` (`colors` is a group in the API but is backed by global colors). |
| Presets (Divi 5) | Option `et_divi_builder_global_presets_d5` → `module` → `divi/<module>` → `{ default, items: { <id> => { id, name, moduleName, version, attrs, styleAttrs, renderAttrs } } }` |
| Divi 4 presets (legacy) | `et_divi_builder_global_presets_ng` |

## ID formats ✅

- Global colors: the key must start with `gcid-`. The sanitizer drops anything else. Divi's own customizer colors use **non-UUID** IDs such as `gcid-primary-color`, which proves that readable IDs work.
- Global variables: the key must match `/^gvid-[a-zA-Z0-9_-]+$/`. Status must be `active` or `archived`. Active variables need a non-empty `value` and a `label`.
- **Plan:** use stable, readable IDs that are identical on every site, e.g. `gcid-dbuddy-primary` and `gvid-dbuddy-h1-size`. Library sections can then reference the same IDs everywhere. ❓ Confirm that the Visual Builder accepts a non-UUID `gvid-` ID once we create one.

### Stored shapes as Divi actually saves them ✅

Divi's own IDs are 10 random lowercase alphanumeric characters, e.g. `gcid-j18rmkfenx` and `gvid-1zerxt6x1m`.

```php
// et_divi['et_global_data']['global_colors']
'gcid-j18rmkfenx' => [ 'id' => 'gcid-j18rmkfenx', 'label' => 'Test Variable', 'color' => '#fd5858',
  'order' => '6', 'status' => 'active', 'folder' => '', 'lastUpdated' => '2026-11-16T23:23:54.879Z', 'usedInPosts' => [] ]

// et_divi_global_variables
'numbers' => [ 'gvid-1zerxt6x1m' => [ 'id' => 'gvid-1zerxt6x1m', 'label' => 'Top Margin', 'value' => '40px',
  'order' => '1', 'status' => 'active', 'lastUpdated' => '…', 'variableType' => 'numbers' ] ]
```

## How content references globals ✅

- In CSS output, variables become CSS custom properties: `var(--gvid-<id>)`. Global colors become `var(--gcid-<id>)`.
- Variable values may be complex CSS such as `clamp()`, `calc()`, `rem` or `vw`. Divi treats them as opaque strings, which makes fluid type sizes possible.
- In module attributes, references are stored as `$variable(<json>)$` tokens. Inside block-comment JSON, the quotes are escaped as `\u0022`. Real examples from saved content:
  - Number variable (padding, font size): `$variable({"type":"content","value":{"name":"gvid-3cytnsrj13","settings":{}}})$`
  - Global color: `$variable({"type":"color","value":{"name":"gcid-j18rmkfenx","settings":{}}})$`
- Example locations: `module.decoration.spacing.desktop.value.padding.top`, `content.decoration.headingFont.h2.font.desktop.value.size` and `…value.color`.

## Content format ✅

- Pages store Divi 5 content as **block comments** in `post_content`, wrapped in a placeholder block:
  `<!-- wp:divi/placeholder --><!-- wp:divi/section {json} --> … <!-- /wp:divi/section --><!-- /wp:divi/placeholder -->`
- Module names: `divi/section`, `divi/row`, `divi/column`, `divi/text`, `divi/image`, `divi/button`, …
- Attributes are nested as `{ element: { group: { breakpoint: { value: … } } } }`. Breakpoints: `desktop`, `tabletWide`, `tablet`, `phoneWide`, `phone`.
- Presets are applied with `"modulePreset": ["<preset id>"]`.
- Every block carries `"builderVersion": "5.19.0"`.
- Page meta: `_et_pb_use_builder = on`, `_et_pb_use_divi_5 = on`.

## Divi Library ✅

A saved section is an `et_pb_layout` post:
- `post_status`: `publish`
- `post_content`: the same `divi/placeholder`-wrapped block content as a page.
- Meta: `_et_pb_built_for_post_type = page`, `_et_pb_template_type = section`, `_et_pb_use_divi_5 = on`
- Terms: `layout_type = section`, `scope = non_global`, `module_width = regular`. Other taxonomies: `layout_category`, `layout_tag`, `layout_pack`.
- Use `layout_category` (e.g. a "Divi Buddy" category) to group our sections in the library UI.

### Export file format (context `et_builder_layouts`)

```
{ context: "et_builder_layouts",
  data: { "<post id>": { …full WP_Post fields…, post_meta: { key: [values] }, terms: { "<term id>": { name, slug, taxonomy, parent, all_parents, description } } } },
  presets: { module: { "divi/image": { default, items: { <id>: {…} } } } },  // only presets the layout uses
  global_colors: [ [ "gcid-…", {…} ], … ],                                    // list of [id, data] pairs
  global_variables: [ { id, label, value, status, type }, … ],
  canvases: [], thumbnails: [],
  images: { "<original url>": { url, id, encoded: "<base64>" } } }           // images embedded as base64 (big!)
```

### Verified with Divi Buddy's library sync ✅

An `et_pb_layout` post created with `wp_insert_post` plus the meta and terms above (and a `Divi Buddy`
`layout_category`) shows up immediately in `ET_Builder_Library::instance()->builder_library_layouts_data( 'section' )`,
the list the builder uses. Items are under `['layouts_data']['items']` and categories under
`['layouts_data']['categories']`. The list includes trashed items (`post_status` publish + trash).
There's no cache to clear.

### Why Divi's own importer isn't reusable

`ET_Core_Portability::import()` in `core/components/Portability.php` is tied to `$_POST`, `$_FILES` and temp files. `import_posts()` also **skips any layout whose title already exists**, so it can't update sections. Divi Buddy needs its own small importer that does `wp_insert_post`/`wp_update_post` with the meta and terms above, keyed on a Divi Buddy ID meta (e.g. `_dbuddy_library_id`).

### Section portability rules (learned from the test export)

Content from one site contains **site-specific references** that break on other sites:
- **Image URLs and attachment IDs:** e.g. `"src":"http://divi-buddy-dev.local/…jpg","id":"12"`.
- **Preset IDs:** `"modulePreset":["mq7c6m75lq"]` only exists on the site that created it.
- **Random global color and variable IDs:** e.g. `gcid-j18rmkfenx`.

So library sections must:
- Reference only `gvid-dbuddy-*` and `gcid-dbuddy-*` globals (or the built-in `gcid-primary-color` etc.). Build sections on a dev site that has Divi Buddy installed, so these IDs exist there.
- Use no module presets (or only presets Divi Buddy ships with stable IDs; not in v1).
- Use placeholder images bundled with the plugin, or none. A build or validation step should flag any other URL, attachment ID, preset ID or non-Divi Buddy `gcid-`/`gvid-` reference.

## CSS cache ✅

- `ET_Core_PageResource::remove_static_resources( 'all', 'all', true )` clears all of Divi's generated CSS. Both setters call it automatically.

## Sync test results (v0.1.0 on Divi 5.19.0) ✅

- Readable IDs work through the API. `gcid-dbuddy-accent` was stored by `set_global_colors( …, true )` next to the site's own `gcid-j18rmkfenx`, which was preserved. All 24 `gvid-dbuddy-*` numbers variables were stored by `import_global_variables()` next to the site's own variables, which were also preserved.
- Divi prints variables on the front end as `--gvid-dbuddy-size-h1: clamp(…);`, so `clamp()` values pass through untouched.
- Setting `heading_font` with `et_update_option` makes Divi load that Google Font on the front end.
- `get_global_colors()` and `get_customizer_colors()` cache the built-in colors in a **static variable for the whole request**. After a write, read the built-in colors from `et_get_option( <option_name> )`, not from `get_global_colors()`.
- `--gcid-dbuddy-*` colors were **not** in the page output when no module used them. Assumed to be normal (Divi only outputs the colors in use), but not yet confirmed.

- ✅ The owner confirmed in the Visual Builder that the Divi Buddy colors and variables appear in the pickers and work.

## Open items

None.
2. ❓ Decide whether Divi Buddy variables should be locked against editing in the Visual Builder (the `allowedActions` flags), so clients only change them through the settings page.
