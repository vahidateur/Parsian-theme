# Third-Party Notices and Licensing Inventory

This file records third-party components, external services, and assets whose
licensing or provenance could not be fully established from repository evidence.
It is part of the project's licensing documentation and does not modify any
license text.

Paths in this file are relative to the repository root. Copies placed inside the
theme or plugin directories keep the same wording for consistency; interpret the
paths relative to their original repository location.

The project's original source code is dual-licensed under Apache-2.0 OR
GPL-2.0-or-later. **Nothing in this file is relicensed by that grant.**

## Classification summary

| Class | Description | Treatment |
|---|---|---|
| 1 | Original project code | Apache-2.0 OR GPL-2.0-or-later (see `LICENSE`, `NOTICE`) |
| 2 | Third-party code with known license | Used under its own license; attribution recorded below |
| 3 | Third-party assets with known license | Used under its own license; attribution recorded below |
| 4 | Assets with unclear or unverified provenance | **Excluded** from the project license grant; owner confirmation required |
| 5 | Generated/compiled artifacts | Not separately licensed; provenance noted below |

## 1. Original project code

The PHP, JavaScript, and CSS authored for this repository (theme `parsian-music/`,
companion plugin `parsian-assistant/`, governance scripts `.agents/`, and local
tooling) is original project code, subject to the dual-license structure above.
This classification is based on the repository's declared authorship; it is not
a legal determination of copyright ownership. The repository owner should
confirm that all contributions were made by the copyright holder or under terms
that permit this dual licensing.

## 2. Third-party code with known licenses

### jsdom 31.0.0

- Type: Development dependency (JavaScript test environment)
- Source: <https://github.com/jsdom/jsdom>
- Declared in: `wp-local/tests/js/package.json`
- License: MIT
- Redistribution: Not distributed in the theme or plugin ZIP archives. It is
  installed locally/CI via `npm ci` for popup regression tests only.
- Attribution: Copyright jsdom contributors; MIT license terms apply to the
  dependency. No jsdom source is vendored in this repository.

### jQuery

- Type: JavaScript library
- Source: <https://jquery.com/>
- Usage: `wp_enqueue_script( 'jquery' )` via WordPress core; `admin.js` and
  `admin-ui.js` consume the `jQuery` global.
- License: MIT (as distributed by WordPress; WordPress core is GPL-2.0-or-later)
- Redistribution: Not bundled in this repository; provided by WordPress.

### WordPress, WP-CLI, MySQL, MariaDB, Node.js, npm

- Type: Runtime, CI, and development tooling
- Redistribution: Not bundled in this repository. CI downloads or provisions
  these tools at run time. Their own licenses apply.

## 3. Third-party assets with known licenses

### Vazirmatn font

- Type: Web font
- Source: <https://github.com/rastikerdar/vazirmatn>
- Provider: Google Fonts (`https://fonts.googleapis.com/css2?family=Vazirmatn:...`)
- License: SIL Open Font License, Version 1.1 (OFL-1.1)
- Redistribution: The font files are **not** bundled in this repository. The
  theme links to Google Fonts at run time. OFL-1.1 applies to the font software;
  the theme's own code is not relicensed by OFL-1.1.
- Attribution: Vazirmatn by Saber Rastikerdar and contributors, licensed under
  the SIL Open Font License 1.1.

## 4. Assets with unclear or unverified provenance (EXCLUDED)

The following files are present in the repository but have **no documented
source, copyright holder, license, or attribution** in repository history,
file metadata, or documentation. They are therefore **excluded from the
Apache-2.0 / GPL-2.0-or-later grant** and must not be redistributed as part of a
licensed package until the owner confirms the rights or replaces them.

| Resource | Type | Path | Source / copyright | License | Redistribution status |
|---|---|---|---|---|---|
| conductor.jpg | Photograph/illustration | `parsian-music/assets/images/conductor.jpg` | Not documented; no EXIF copyright/artist | Unknown | Excluded |
| dept-kids.jpg | Photograph/illustration | `parsian-music/assets/images/dept-kids.jpg` | Not documented; no EXIF | Unknown | Excluded |
| dept-persian.jpg | Photograph/illustration | `parsian-music/assets/images/dept-persian.jpg` | Not documented; no EXIF | Unknown | Excluded |
| dept-piano.jpg | Photograph/illustration | `parsian-music/assets/images/dept-piano.jpg` | Not documented; no EXIF | Unknown | Excluded |
| dept-strings.jpg | Photograph/illustration | `parsian-music/assets/images/dept-strings.jpg` | Not documented; no EXIF | Unknown | Excluded |
| dept-theory.jpg | Photograph/illustration | `parsian-music/assets/images/dept-theory.jpg` | Not documented; no EXIF | Unknown | Excluded |
| dept-vocal.jpg | Photograph/illustration | `parsian-music/assets/images/dept-vocal.jpg` | Not documented; no EXIF | Unknown | Excluded |
| duet.webp | Photograph/illustration | `parsian-music/assets/images/duet.webp` | Not documented; no EXIF | Unknown | Excluded |
| hall.jpg | Photograph/illustration | `parsian-music/assets/images/hall.jpg` | Not documented; no EXIF | Unknown | Excluded |
| hero.jpg | Photograph/illustration | `parsian-music/assets/images/hero.jpg` | Not documented; no EXIF | Unknown | Excluded |
| masterclass.webp | Photograph/illustration | `parsian-music/assets/images/masterclass.webp` | Not documented; no EXIF | Unknown | Excluded |
| portrait-persian.webp | Photograph/illustration | `parsian-music/assets/images/portrait-persian.webp` | Not documented; no EXIF | Unknown | Excluded |
| portrait-theory.webp | Photograph/illustration | `parsian-music/assets/images/portrait-theory.webp` | Not documented; no EXIF | Unknown | Excluded |
| portrait-vocal.webp | Photograph/illustration | `parsian-music/assets/images/portrait-vocal.webp` | Not documented; no EXIF | Unknown | Excluded |
| student-after.webp | Photograph/illustration | `parsian-music/assets/images/student-after.webp` | Not documented; no EXIF | Unknown | Excluded |
| student.jpg | Photograph/illustration | `parsian-music/assets/images/student.jpg` | Not documented; no EXIF | Unknown | Excluded |
| teacher-karimi.webp | Photograph | `parsian-music/assets/images/teacher-karimi.webp` | Not documented; no EXIF | Unknown | Excluded |
| teacher-kids.svg | SVG illustration | `parsian-music/assets/images/teacher-kids.svg` | No third-party notice; presumed project-created | Unverified | Excluded pending confirmation |
| teacher-persian.svg | SVG illustration | `parsian-music/assets/images/teacher-persian.svg` | No third-party notice; presumed project-created | Unverified | Excluded pending confirmation |
| teacher-piano.svg | SVG illustration | `parsian-music/assets/images/teacher-piano.svg` | No third-party notice; presumed project-created | Unverified | Excluded pending confirmation |
| teacher-placeholder.svg | SVG illustration | `parsian-music/assets/images/teacher-placeholder.svg` | No third-party notice; presumed project-created | Unverified | Excluded pending confirmation |
| teacher-theory.svg | SVG illustration | `parsian-music/assets/images/teacher-theory.svg` | No third-party notice; presumed project-created | Unverified | Excluded pending confirmation |
| teacher-vocal.svg | SVG illustration | `parsian-music/assets/images/teacher-vocal.svg` | No third-party notice; presumed project-created | Unverified | Excluded pending confirmation |
| icons.svg | SVG icon sprite | `parsian-music/assets/icons.svg` | No third-party notice; custom `i-*` symbols | Unverified | Excluded pending confirmation |
| screenshot.png | Theme screenshot | `parsian-music/screenshot.png` | Generated from the theme; may depict excluded images | Unverified | Excluded pending confirmation |

### Note on the screenshot and demo content

The theme README describes the teacher images as demo/sample content and
instructs operators to replace them with real teacher images. `screenshot.png`
is a theme screenshot and may incorporate the excluded images. Treat these files
as placeholders, not as licensed stock assets.

### Recommended owner actions

1. Confirm authorship/ownership of all images, SVG illustrations, the icon
   sprite, and the screenshot; or replace them with assets carrying a documented
   license (e.g., original photography, CC0, or a compatible stock license).
2. Record the source URL, copyright holder, and license for each replacement in
   this file.
3. If any asset is third-party, verify that its license is GPL-compatible before
   including it in a WordPress theme/plugin distribution.

## 5. Generated and compiled artifacts

- `wp-local/tests/js/package-lock.json` — generated by npm; describes the jsdom
  dependency tree and integrity hashes. It is not a creative work of the project.
- `.github/workflows/` — original project CI configuration.
- No minified or compiled theme/plugin assets are committed; source files are
  served directly.

## External services

- **Google Fonts** (`fonts.googleapis.com`) — used to load the Vazirmatn
  stylesheet at run time. No font files are redistributed by this repository.
- **Bale** (`tapi.bale.ai`) and **Rubika** (`botapi.rubika.ir`) — optional
  messaging providers configured by the site administrator.
- **AI provider** — an OpenAI-compatible endpoint configured by the site
  administrator. No provider credentials or content are included in this
  repository.

These services are governed by their own terms. This repository does not grant
rights to use them.

## Verification status

- Repository evidence: file inventory, headers, manifests, and metadata checked.
- Image EXIF metadata: no copyright/artist/source tags found in the raster files.
- No third-party license files were present in the repository before this
  document was added.
- This inventory is a good-faith technical assessment, not legal advice. The
  repository owner should obtain qualified legal review before commercial
  redistribution or WordPress.org submission.
