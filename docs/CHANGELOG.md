# Changelog

All notable changes made to NeoCMS in this contribution: the `cms/` directory, `uploads/.htaccess`, `README.md`, `docs/`, and the updated `tests/run.php`.

This round builds on pull request #39 (site scan, editor workflow, subfolder support), which is already merged upstream and is summarised at the end.

## Unreleased

### Added
- **Navigation menus from the scan**: the site scan detects each page's header `<nav>` (primary) and footer `<nav>` (secondary), marks it `data-neo-menu` in place, and pre-fills Navigation Menus from it (a saved menu is never overwritten). Menu saves now keep the nav's own attributes, leave other bytes alone, skip unchanged pages and set `aria-current` per page. The menu form only appears after choosing Edit on an existing menu, and the CMS cannot create menus (they come from the site's pages via the scan); the display name can be changed without affecting the menu's key. Menus can be deleted (pages keep their current navigation). Navigation Menus has a "Scan site for new navigation" button that finds navigation blocks not yet attached to a menu (including ones a developer adds later) and exposes them. The Navigation Menus button moved to the main toolbar.
- **New page wizard**: New Page is now three steps (template, page name, navigation group). The filename is generated from the name (`about-us.html`, numbered if taken). The page is saved only as a private draft, so it is not visible on the site until first published; publishing creates the file and adds a link to the chosen menu on every page. Unpublished pages show as "New - unpublished" in Pages and can be discarded. Scheduling works for them too.
- **Recover deleted pages**: the Dashboard lists deleted pages (those whose newest revision is "Before delete" and whose file is gone) with a Recover button for editors, which recreates the page from its snapshot. New `deleted` field in the `dashboard` response.
- **Consistent buttons**: row buttons (Edit, Delete, ...) and other dialogue buttons use one small size (12px, 4px 9px padding, 4px radius); action buttons (editor Save Draft/Schedule/Publish/Cancel, dialogue Save/Next/Back/Apply) are a step larger (13px, 6px 14px). Secondary buttons are white with a border, destructive ones have red text, and primary actions (Save, Next, Apply) are filled blue. Editor buttons keep their Save Draft blue text and green Publish Now.
- **Top bar**: a full-width grid. The logo and page path fill the left; the user name and Log out are always at the top right, with the menu buttons and device icons directly beneath them. Below 1100px the buttons take their own row; below 760px everything stacks but the user block stays top right. The logo URL is now relative so it shows in a subfolder install.
- **Users** (Tools > Users): every user can update their display name and email address and change their password; administrators can add, edit, invite (one-time link, 7 days), block, unblock and delete accounts. Sign-in uses the login name only; the email address is contact information. Accounts in `config.local.php` stay controlled by config (only display name and email can be set for them); CMS accounts live in `data/users.json`. Roles are now **Editor** (draft, upload, publish, schedule, restore) and **Administrator**; `publisher` remains an alias of editor. Security: password hashes only (12 to 72 characters), no secrets in responses, invite tokens stored hashed and single use, administrator password confirmation for sensitive actions, rate-limited failures, self-lockout guards, sessions of blocked/deleted/re-passworded users end on their next request, every account event audited. New `UserStore` class; actions `users`, `saveProfile`, `changePassword`, `saveUser`, `inviteUser`, `blockUser`, `deleteUser`.
- **Documentation**: README.md and docs/ARCHITECTURE.md are now kept in step with the changes (roles and Users, navigation menus, New Page wizard and templates, deleted-page recovery, subfolder installs, interface) and are tracked in the local history.
- **Data outside the web root**: `dataDirectory` is honoured everywhere (verified live: accounts, rate limiting, activity and the cron worker all use it; the dashboard writable check now checks it too). Administrators see a Dashboard recommendation while the data folder is inside the web root, and the README install steps and `config.local.php.example` describe the setting.
- **Login name wording**: the Users screen and login page call the username a "login name" (shown read-only in My account and in the account list); the email field is labelled as contact details, not a sign-in identifier.
- **Media library for images, documents, video and audio**: Media now uploads files (multiple at once) into four categories chosen by extension (Imagery, Documents, Video, Audio), with category tabs and counts, per-file description, Copy link and Delete. The editor has a **Media library** button that inserts an image, a document link, a video or an audio player at the cursor. Safety: an extension allow-list (no `.exe`, scripts, HTML/SVG, archives, legacy or macro Office files), filenames such as `report.exe.pdf` refused, content sniffing, refusal of executable signatures and of any file containing `<?php`, `<?=`, `<script` or `<%`, PDFs with JavaScript/launch/embedded files refused, and Office/OpenDocument packages inspected for macros, embedded objects and scripts (built-in ZIP reader, no `zip` extension needed). New `MediaTypes` class; `image_upload.php` now handles all media and returns `name` and `category`; `mediaAction` adds `category`, `ext` and `original`; new stored names are `slug-8hex.ext` (legacy names still work). `uploads/.htaccess` (outside `cms/`) denies more executable extensions and serves documents as downloads. TinyMCE toolbar now wraps so all buttons are visible. The code scan only looks for the long markers (`<?php`, `<script`) in binary files and adds the short ones (`<?=`, `<%@`, `<%=`) for text files only, because compressed video and audio contain short byte sequences by chance; audio and video whose type libmagic cannot name are accepted when their container signature matches; oversize files report the limit.
- **New page merged into Pages**: the New Page toolbar button and its separate dialogue are gone. Pages is now one dialogue with the page list on top and, for administrators, a **New page** section beneath it holding the same three-step wizard (template, page name, navigation group). Creating a page closes the dialogue and opens the private draft for editing. The dialogue scrolls inside the window when it is tall.
- **Welcome page**: updated the built-in welcome sections for the New Page wizard, templates, navigation menus, site scan and device icons.
- **Pages as templates**: each row in the page list has a "Create Template" action (administrators). The page is then offered in New Page beside the files in `cms/templates/`; the new page is a private copy with its title replaced. Renaming or deleting a template page keeps the list in step.
- **Template list**: the New Page template and navigation-group lists use the page-table styling; each template has a Delete action (a template file is deleted; a page used as a template is only unmarked).
- **Device preview**: the Desktop/Tablet/Mobile buttons are icons (with tooltips and accessible names) and the active one is highlighted.

### Migration notes
- New Page is now a section of the Pages dialogue (there is no New Page toolbar button).
- Roles: `publisher` is merged into `editor` (editors can now publish and schedule). Existing `publisher` assignments keep working as an alias; review any account that was a plain `editor`, because it can now publish.
- Accounts: config accounts are unchanged. CMS-created accounts are stored in `cms/data/users.json`. Sign-in is by login name only.
- Media: new uploads are named `slug-8hex.ext` and accept documents, video and audio; existing 32-hex image names keep working. Copy the updated `uploads/.htaccess` (outside `cms/`).
- New optional setting `dataDirectory`: set it to a folder outside the web root on live sites. The default `cms/data/` keeps working.
- Navigation menus can no longer be created from the CMS; they are discovered by the site scan (existing menus are kept). New pages are private drafts until published.
- New API actions: `users`, `saveProfile`, `changePassword`, `saveUser`, `inviteUser`, `blockUser`, `deleteUser`, `newPage` (changed), `discardNewPage`, `setPageTemplate`, `deleteTemplate`, `deleteMenu`; the dashboard response gains `deleted` and `notices`; `media` items gain `category`, `ext` and `original`.

### Verification
- The updated integration suite (`tests/run.php`, included in this contribution) passes, covering concurrency, site scan and tagging, menus, new pages
  and templates, deleted-page recovery, users and roles, invitations, media type checks, and audit-log cases. Upstream's earlier `tests/run.php` expects
  behaviour that has intentionally changed (editors cannot publish, menus can be created, New Page copies immediately), so the updated suite replaces it.
  On Windows the Linux-only permission assertions are skipped.

## Merged in pull request #39

### Added
- **Site scan** (Tools > Site scan, administrators): analyses every managed page and the media library, then makes pages editable
  in one step.
  - Reports page and folder counts, pages that can be made editable, images without alt text, broken local image/CSS/script
    references, unused uploads, and missing title, description and Open Graph tags.
  - Tags content containers (`<main>`, else top-level `<article>`/text-bearing `<section>`; header, nav, footer, aside and
    forms are skipped, `<body>` is never tagged), images outside editable regions (`data-neo-image`) and photo placeholders
    (`role="img"` boxes), and inserts only missing SEO tags. Existing tags are never overwritten.
  - Edits page source byte-for-byte (a tokenizer splices markers in; no DOM re-serialisation), keeps a revision of every changed
    page, and after the first run lists only pages that still have something to change.
  - Runs in small batches from the browser with a progress bar and "N of M pages" count, for both analysing and updating.
  - New administrator-only actions: `listSitePages`, `analyseSitePages`, `applySiteTagging`.
- **Click-to-edit images and photo placeholders**: clicking an image (or a photo-placeholder box) opens an image dialog to pick from
  the Media Library or change the address and alt text. Clicking other text still opens the content editor.
- **Subfolder installs**: the site can live in a subfolder (for example `http://localhost/neocms/`). The URL prefix is detected
  automatically (`basePath`), the site root is the folder containing `cms/` (`siteRoot`), and the session cookie path follows.
  Root installs behave exactly as before.
- **Audit log coverage**: cancelling a schedule, uploading an image, changing alt text and failed scheduled publications are now
  recorded in the Dashboard activity feed and the daily audit log (new shared `Activity` class).
- **New interface**: bottom-centre snackbar with success/error states and a close button; table-style dialogs (page picker, revision
  history, dashboard, shared content, navigation menus, site scan) with icons, draft/status badges and per-row actions.

### Changed
- **Editing workflow**: Save Draft, Schedule Publish and Publish Now moved from the main toolbar into the content editor, which now
  offers `Save Draft | Schedule Publish | Publish Now | Cancel`. Saving in the editor (and applying image/SEO changes) stores a
  private draft; the header shows "Draft:" when one exists. Publish Now keeps the accessibility confirmation and the editor stays
  open if it is cancelled.
- **Structure**: `CMSController` split into `PagePaths` (URI/path safety), `ContentDom` (HTML transforms) and `Activity`; entry
  points share `cms/bootstrap.php` (config + autoloader) instead of four copies.
- Restoring a deleted page now uses a `PageNotFoundException` instead of matching an error message.

### Fixed
- **Lost updates**: read-modify-write of JSON metadata is now serialised with `FileStore::update()` (file locking).
- **Non-atomic site-wide writes**: shared-content and menu propagation now replace pages atomically, and non-ASCII text survives.
- **Scheduled publishing** no longer contends for a lock on every request and cannot fail unrelated API calls.
- **Revision pruning** could drop the newest revision when several were created in the same second; revisions now carry a
  precise `ts`.
- **Stale preview**: pages replaced on disk could show an old cached copy in the editor preview; each newly opened page is revalidated.
- **Draft reload**: a saved draft loaded into the preview lost its stylesheets and page path (relative URLs resolved inside `/cms/`),
  and could make Publish target the wrong address.
- Page titles containing HTML entities are decoded in the page list; PHP `dirname()` backslashes no longer leak into folder names.
- Draft prompts are offered again after a draft is saved within the same session (previously Publish could discard the draft).

### Migration notes
- No manual steps. New optional config keys `basePath` and `siteRoot` are auto-detected (`bootstrap.php`); set `basePath` only
  behind a path-rewriting proxy.
- `revisions.json` entries gain a `ts` field (older entries continue to work).
- The `analyseSite` action was replaced by `listSitePages` and `analyseSitePages`.
- The toolbar no longer has Save Draft, Schedule or Publish buttons; they are in the content editor.
