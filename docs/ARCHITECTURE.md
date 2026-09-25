# NeoCMS Architecture

## Design Goals

NeoCMS adds controlled visual editing to static HTML websites while retaining three properties:

* Public pages remain ordinary HTML files served directly by the web server.
* Supporting state uses files rather than a database service.
* Existing page structure and styling remain under the site's control.

## Request Flow

`cms/index.php` renders the authenticated administration shell. It passes the CSRF token and configured editable class to
`cms/js/cms.js` through metadata tags. The JavaScript client loads public pages into a same-origin iframe, binds editing
behaviour to matching regions, and submits complete serialised documents to `cms/controller/index.php`.

The preview iframe uses `sandbox="allow-same-origin"` without `allow-scripts`. Parent-side JavaScript may still inspect and
edit the DOM, while scripts belonging to public pages cannot reach the administration window, session, or CSRF token.

Every entry point loads `cms/bootstrap.php`, which reads `config.php` and the untracked `config.local.php`, works out the URL prefix
(`basePath`) and site root (`siteRoot`) for subfolder installs, and registers the class autoloader.

`CMSController` is the single API dispatcher. It authenticates every request and delegates mutating actions through
`requirePost()`, which enforces POST, CSRF validation, and the requested role capability.

Media uploads (images, documents, video, audio) use the separate `cms/image_upload.php` endpoint because TinyMCE and the Media
library send multipart file data. It applies the same authentication, role, and CSRF requirements before `MediaTypes::inspect()`
validates the file (see Media below).

## Components

| Class | Responsibility |
| --- | --- |
| `CMSController` | Action dispatcher and workflows (pages, drafts, publishing, menus, templates, users). |
| `Authentication` | Sessions, role capabilities, CSRF tokens, session revocation. |
| `UserStore` | Account registry: config accounts plus CMS accounts, invitations, password policy. |
| `PagePaths` | URI and path safety (containment, symlinks, existing and new page paths). |
| `ContentDom` | HTML transforms: menus, shared regions, link resolution, image and SEO edits. |
| `MediaTypes` | The allow-list of media extensions and categories, and the upload safety checks. |
| `SiteAnalyser` | Byte-preserving tokenizer used by the site scan and menu tagging. |
| `FileStore` | Locked, atomic JSON metadata storage. |
| `Activity`, `Logger` | Dashboard activity feed and daily audit log. |
| `LoginRateLimiter` | Hashed address and identity throttling, also used for password confirmation and invites. |

## Storage Model

Published content stays in the web document root. NeoCMS stores supporting data beneath `cms/data/`:

| Path or document | Contents |
| --- | --- |
| `drafts/` | Complete unpublished HTML documents, named by a hash of their URI. New pages live only here until first published. |
| `revisions/` | Immutable snapshots of earlier published documents. |
| `scheduled/` | Complete HTML staged for future publication. |
| `drafts.json` | Human-readable draft ownership and timestamp metadata. |
| `revisions.json` | Revision URI, author, reason, and timestamp index. |
| `schedules.json` | Publication times and staged-document identifiers. |
| `shared.json` | Named global HTML fragments. |
| `menus.json` | Named menus: `{key: {items: [{label, url, parent}], updated, title?}}`; seeded by the site scan, never created by the CMS. |
| `newpages.json` | Pending (unpublished) pages: title, chosen menu, template, author, created. |
| `pagetemplates.json` | Pages offered as templates in the New page section of Pages. |
| `users.json` | CMS-created accounts and profile overlays for config accounts (`{users: {...}, profiles: {...}}`). |
| `media.json` | Per-file description (alt text or link text), original filename, and uploader. |
| `activity.json` | The latest 250 dashboard activity entries. |
| `login-attempts.json` | Hashed address and identity buckets used for login throttling. |

`FileStore` replaces JSON documents atomically by writing a temporary neighbour and renaming it. Public page publication
uses the same approach. A revision is created before replacing, deleting, or globally updating a public page. Private
directories use mode `0700` and private files use `0600` where supported. Configurable count and byte quotas prevent drafts,
schedules, revisions, and uploads from growing without bound.

## Editing Model

The configured `editableClass` identifies editable regions. The client edits a region's inner HTML in TinyMCE, then writes
it back to the iframe only. Drafting, scheduling, and publishing serialise a clone of the complete iframe document after
removing temporary CMS controls.

`neo-dupe` marks a repeatable block. `data-neo-shared="key"` marks a globally managed fragment. `data-neo-menu="name"`
marks a `<nav>` whose list is replaced whenever its named menu changes. `data-neo-image` marks an image outside an editable
region that can be replaced through the image dialogue.

The bundled templates use the default `editable` class. Sites that override `editableClass` must update their templates
to match; otherwise the resulting page is valid HTML but, quite reasonably, is not treated as editable by NeoCMS.

## Site Scan

`SiteAnalyser` tokenises each page (skipping comments, scripts, styles, and templates) to locate element offsets, then
`apply()` splices markers into the original bytes. It deliberately avoids DOM re-serialisation so an automated pass over
every page cannot rewrite unrelated markup. The browser drives the scan in small batches so it can show a progress bar and page count: `listSitePages` returns the
page list, `analyseSitePages` reports a plan for up to 100 pages per request (the client totals the results), and
`applySiteTagging` reuses `rewritePages()` to write only the pages it is given, after taking a revision of each,
through the same atomic-replace path as publishing. All three need the administrator role. Local asset references are resolved and confined by
`PagePaths::refExists()`.

## Media

`MediaTypes` is the single source of truth for uploads. Its allow-list maps each extension to one of four categories (imagery,
documents, video, audio); the category is derived from the extension whenever the library is listed, and there is no separate
category field to drift. `MediaTypes::inspect()` runs for every upload in `cms/image_upload.php` (the endpoint TinyMCE also uses):
extension allow-list plus a deny list applied to every dot-separated part of the original name, content sniffing with `finfo`
against the extension, refusal of executable signatures, a chunked scan of the whole file for code markers, and per-format
checks (image decoding within the size limits, PDF actions, and a small built-in ZIP directory reader for OOXML and
OpenDocument packages that refuses macros, embedded objects, scripts, and executables without needing the `zip` extension).
Files are stored under `uploads/` as `slug-8hex.ext` (one dot only) or as legacy 32-hex names, both matched by
`MediaTypes::nameRegex()`, which the usage counter, the site scan, and `deleteMedia` all share. `uploads/.htaccess` denies
executable and markup extensions, sets `nosniff`, and serves documents with `Content-Disposition: attachment`. The editor's
**Media library** button opens the same dialog in pick mode and inserts an `<img>`, a document link, or a `<video>`/`<audio>`
element by category.

## New Pages and Templates

`newPageAction` reads the chosen template (a file in `cms/templates/` or `page:<uri>` for a page marked in `pagetemplates.json`),
sets its title, and stores it only as a private draft plus a `newpages.json` record. No file is created, so the page cannot be
served. `publishContent` (used by Publish Now and by scheduled publishing) creates the file on first publication, then
`finishNewPage` adds the link to the chosen menu, propagates it, and removes the pending record. The preview writes the draft into
an `about:blank` frame with an injected `<base>` element so relative assets resolve. Page filenames come from the name via
`uniqueUri`, never from user input.

## Navigation Menus

`SiteAnalyser::navs()` classifies each `<nav>` by its position and label (header, banner and main navigation are `primary`;
footer and legal are `secondary`). The scan tags it with `data-neo-menu` and seeds `menus.json` from the first page that has it
(existing menus are never overwritten). `ContentDom::withMenu()` replaces only the inner list of a marked `<nav>` in the page's
original bytes, sets `aria-current="page"` for the current page, and returns null when nothing changes so the page is not
rewritten. The CMS cannot create a menu: `saveMenu` requires an existing key and may change its items and display `title`.

## Deleted Page Recovery

Deleting a page first stores its HTML as a revision with the reason "Before delete". The dashboard's `deleted` list (editors
and administrators) contains pages whose newest revision has that reason and whose file no longer exists; **Recover** calls
`restoreRevision`, which recreates the file from the snapshot.

## Subfolder Installs

`bootstrap.php` derives `basePath` from the script URL and `siteRoot` from the folder containing `cms/`. Stored menu links and
page URLs carry the prefix, and the session cookie path is `basePath/cms`. Both values can be overridden in configuration.

## Roles
Capabilities are intentionally small and cumulative:

| Role | Capabilities |
| --- | --- |
| `editor` | Save drafts, upload images, publish, schedule, restore revisions, and recover deleted pages (the old `publisher` role is an alias). |
| `administrator` | Editor capabilities plus page, media, shared-content, menu, template, and user management. |

The interface hides controls the user cannot invoke, but server-side checks remain authoritative. Hidden buttons are a
convenience, not a security boundary wearing a clever disguise.

Active sessions are bound to the account's current password hash. Removing, blocking, or re-passwording an account invalidates
its sessions on the next request, while roles are re-read on every request so a demotion is immediate.

## Users and Accounts

`UserStore` merges two sources. Accounts in `config.local.php` are authoritative and locked: they are always active, cannot be
edited, blocked, or deleted from the UI, and cannot be shadowed by a CMS account (only a display name and email overlay is
stored for them). Accounts created in the UI live in `data/users.json` as `{hash, role, name, email, status, invite?}` with status
`active`, `blocked`, or `invited`. `UserStore::authArgs()` supplies `Authentication` with the config accounts plus **active** CMS
accounts only, which is why blocked or deleted users lose their sessions without any extra code. Sign-in uses the login name only; the
email address is contact information (unique, but not a login identifier).

Actions: `users` (GET; administrators also receive the account list, never hashes or tokens), `saveProfile` and `changePassword`
(any signed-in user, acting only on their own account taken from the session), and `saveUser`, `inviteUser`, `blockUser`, and
`deleteUser` (administrators). Sensitive actions and email or password changes require the actor's current password, checked
by `confirmPassword()` and throttled with `LoginRateLimiter`. Invitations are 32 random bytes; only the SHA-256 is stored, the link
works once and expires after 7 days, and acceptance goes through `cms/login/?invite=TOKEN`. Passwords are 12 to 72 bytes
(bcrypt truncates beyond 72), must differ from the username and email, and are stored with `password_hash`. Guard rails in the
store and controller refuse self-block, self-delete, self-demotion, duplicate usernames and emails, and any change to a config
account.

## Interface

The administration shell is a full-height grid: logo and page path on the left, the signed-in user and Log out at the top right,
and the toolbar buttons plus the desktop/tablet/mobile icons beneath them (wrapping to their own row below 1100px). Dialogues are
jQuery UI dialogues built from shared helpers (`dataTable`, `rowButton`), with a snackbar for results. Row buttons are small
secondary buttons, action buttons (Save, Next, Apply, and the editor buttons) are a step larger, and the primary action is blue.

## Scheduled Publishing

Every API request opportunistically processes due jobs. Reliable unattended operation comes from invoking
`cms/publish_scheduled.php` through cron. The worker is CLI-only and uses the same publication and revision path as the UI.

## Security Boundaries

NeoCMS relies on PHP sessions with strict mode, HTTP-only cookies, SameSite=Lax, and Secure cookies under HTTPS. Mutating
requests require a constant-time-checked CSRF token. Page paths are canonicalised and constrained to HTML files beneath the
document root, excluding the CMS directory and symbolic links. Uploads are constrained by an extension allow-list, byte size, detected MIME type,
code scanning, image decoding, dimensions, pixel count, file count, and total storage. Login attempts are throttled by hashed address and
address/username buckets. Account data is protected by hashing only, withholding hashes and tokens from every response, requiring
the actor's password for sensitive account actions, rate limiting those checks, and auditing every account event without secrets.

HTML and JSON responses receive Content Security Policy, anti-framing, no-sniffing, referrer, permissions, cross-origin,
cache-control, and conditional HSTS headers. CDN scripts and styles are fixed to explicit versions and protected by SRI.

The supplied Apache rules protect configuration, data, logs, CLI entry points, and uploaded executable extensions. Other
servers must provide equivalent denial rules, and production use should always use HTTPS.

Audit entries are locked while appending, normalised to prevent forged lines, rotated at a configurable size, and pruned
after a configurable retention period. They remain operational records rather than a tamper-proof ledger.
