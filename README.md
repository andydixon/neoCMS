# NeoCMS

## Version 3.0

Welcome to NeoCMS, the lightweight, database-free content management system. Built with PHP and JavaScript, NeoCMS is
designed to add controlled visual editing to an existing static website without changing how public pages are served.

Version 3.0 adds filesystem-backed drafts, revision history, role-based permissions, scheduled publishing, page and media
management, SEO controls, shared content, navigation menus, responsive previews, accessibility checks, and an operational
dashboard. Public content remains ordinary HTML; the database server may continue its well-earned rest.

Further technical documentation:

* [Architecture](docs/ARCHITECTURE.md)
* [Development and testing](docs/DEVELOPMENT.md)

## Features

* No Database Required: NeoCMS works without a database, so you don’t have to worry about setup hassles or performance
  slowdowns.
* Easy Content Editing: The TinyMCE visual editor updates marked content regions without requiring authors to edit HTML.
  Adding `neo-dupe` allows an element and its children to be cloned or removed.
* Seamless Integration: Simply add the CMS to any existing static website. Just assign a special class to the elements
  you want to make editable, and you’re all set.
* Consistent Layouts: NeoCMS supports page templates to keep your layouts neat and uniform across all pages.
* Drafts and Publishing: Clicking Save in the editor keeps the change as a private draft without touching the public page.
  Editors can then publish immediately or schedule a publication.
* Revision History: Every changed or deleted page receives a timestamped filesystem snapshot that editors can restore, and
  deleted pages can be recovered from the Dashboard.
* Page Management: Search, duplicate, rename, and delete editable HTML pages, including pages in subdirectories. The New page section of the Pages dialogue is a
  three-step wizard (template, page name, navigation group); the new page stays a private draft until it is published. Any page can
  be offered as a template with Create Template.
* Media Library: Upload images, documents, video, and audio; they are sorted into Imagery, Documents, Video, and Audio by file
  extension. Reuse them from the editor's Media library button, maintain alternative text or link text, see usage counts, and
  remove files. Executables and files containing code are refused.
* SEO Settings: Edit the page title, description, canonical URL, Open Graph image, and robots indexing setting.
* Shared Content: Mark content with `data-neo-shared="name"` and update matching regions across the site.
* Navigation Menus: The site scan finds each page's primary and secondary navigation and pre-fills the menus. Edit, rename, or
  delete a menu and the change is applied to every page that uses it. Menus are created by the web developer's markup, not by the
  CMS.
* Responsive Preview: Preview the current page at desktop, tablet, and mobile widths using the device icons in the top bar.
* Accessibility Checks: Check common authoring problems such as missing alt text, empty links, heading jumps, missing labels,
  and absent page metadata before publishing.
* Site Scan: Analyse every managed page and the media library, then make pages editable in one step. The scan
  finds content areas, images, navigation, broken local references, and missing SEO tags, and adds the markers NeoCMS needs.
* Users and Roles: Everyone can update their display name, email address, and password in Tools > Users. Administrators can add,
  edit, invite, block, and delete accounts. Two roles, Editor and Administrator, are described on that screen.
* Activity: The dashboard displays drafts, schedules, deleted pages, system problems, and recent activity.

## Installation Guide

To install NeoCMS:

* Place the `cms/` directory in the document root of the static website.
* Place the `uploads/` directory in the same document root.
* Copy `cms/config.local.php.example` to `cms/config.local.php`, add password hashes, and assign roles.
* Update the elements you want to make editable by adding the configured editable class. It is `editable` by default.
* Confirm that PHP may write to the managed HTML files, `cms/data/`, `cms/logs/`, and `uploads/`.
* **Live sites:** create a folder outside the web root (for example `/var/neocms-data`), make it writable by PHP, and set
  `'dataDirectory' => '/var/neocms-data'` in `cms/config.local.php`. It must be an absolute path. Without this, accounts, drafts,
  and revisions live in `cms/data/`, which is protected only by the supplied `.htaccess`. Administrators see a Dashboard
  recommendation until the data folder is outside the web root. Audit logs remain in `cms/logs/`, also protected by `.htaccess`.
* Visit `/cms/`, log in, and begin editing.

The site may also live in a subfolder (for example `http://localhost/mysite/`): the URL prefix is detected automatically. See
[Subfolder installs](#subfolder-installs).

That’s it! You’re up and running.

## Configuration

Tracked defaults live in `cms/config.php`. Deployment credentials and overrides belong in the Git-ignored
`cms/config.local.php`, which must return an array. The supplied Apache rules deny direct access to both files.

| Setting | Type | Purpose |
| --- | --- | --- |
| `authentication` | array | Maps the main administrator usernames to `password_hash()` values; plaintext credentials are rejected. These accounts are managed only here. |
| `roles` | array | Maps those usernames to `editor` or `administrator` (`publisher` is accepted as an alias of `editor`). |
| `security` | array | Configures session expiry, login throttling, request limits, storage quotas, and audit-log retention. |
| `uploads` | array | Configures the per-file size limit (all media types), image dimension, file-count, and aggregate-storage limits. |
| `audit` | boolean | Enables daily activity logs under `cms/logs/`. |
| `skipWelcomePage` | boolean | Opens the public root instead of the built-in guide after login. |
| `showFullUrl` | boolean | Displays the current page path in the toolbar. |
| `editableClass` | string | Selects the single CSS class used to identify editable regions. |

`dataDirectory` is the absolute path of the folder holding accounts (`users.json`), drafts, revisions, schedules, and menus. It
defaults to `cms/data/`; on a live site, point it at a folder outside the web root (see the Installation Guide) so those files
can never be served. The folder is created on first use if PHP may create it, and the CLI scheduler uses the same setting.

`basePath` (URL prefix of the site root, such as `/mysite`) and `siteRoot` (the folder containing `cms/`) are detected
automatically and only need setting behind a path-rewriting proxy.

### Editable Class

The class used to identify editable regions is configurable:

```php
'editableClass' => 'editable',
```

Set it to any single valid CSS class name without a leading dot, for example:

```php
'editableClass' => 'cms-content',
```

Your page markup must then use that class:

```html
<section class="cms-content">This region can be edited.</section>
```

Invalid configured values fall back to `editable`. The `neo-dupe` class remains fixed because it marks repeatable blocks,
not general editable content. When overriding `editableClass`, update the classes in `cms/templates/` as well so newly
created pages are immediately discoverable by the page picker.

### Users and Roles

There are two kinds of account:

* **Config accounts** are the main administrators. Generate a hash with `password_hash()` and store it under `authentication` in
  `cms/config.local.php`, with a role under `roles`. They can be changed only in that file (in Tools > Users they are listed as
  "Config"; only their display name and email can be set from the CMS).
* **CMS accounts** are created in **Tools > Users** and stored in `cms/data/users.json`.

```php
'authentication' => [
    'admin' => '$2y$10$...',
],
'roles' => [
    'admin' => 'administrator',
],
```

There are two roles:

| Role | Can do |
| --- | --- |
| `editor` | Edit content, save drafts, upload images, publish or schedule pages, and restore revisions or deleted pages. Cannot change the site structure or manage users. |
| `administrator` | Everything an editor can, plus creating new pages, duplicate, rename and delete pages, templates, navigation menus, shared content, site scan, deleting media, and managing users. |

The former `publisher` role is now part of `editor`; existing `publisher` assignments are treated as `editor`. Users without an
explicit valid role receive `editor`, the least powerful role.

#### The Users screen

* **My account** (every user): see your login name (set by an administrator), change the display name, the email address (needs the
  current password), and the password (current, new, confirmation; CMS accounts only). You sign in with your login name and
  password; the email address is contact information only.
* **Roles**: a description of each role, with your own marked.
* **User accounts** (administrators): add an account with a password, edit an account (name, email, role, optional password
  reset), invite someone, block or unblock, or delete. Add, edit, invite, block, and delete ask for the administrator's own
  password. You cannot block, delete, or demote yourself, and config accounts cannot be changed here.
* **Invitations** produce a one-time link that is shown once and expires after 7 days. The invitee opens it and chooses their own
  password. No email is sent by the CMS.

#### Account protection

Passwords are 12 to 72 characters and are stored only as hashes. Invite tokens are stored only as hashes and work once. Secrets
never appear in API responses or the activity log. Repeated wrong passwords are rate limited, every account event is recorded in
the activity feed and audit log, and blocking, deleting, changing a role, or changing a password affects that user's sessions on
their next request. `data/.htaccess` denies web access to `users.json`.

Sessions expire after 30 minutes of inactivity and 12 hours in total by default.

When HTTPS terminates at a trusted reverse proxy, set `'cookieSecure' => true` beneath `security` in the local override.
This keeps the session cookie marked Secure even when PHP receives the proxy's internal HTTP request.

Audit files rotate at 10 MiB and are retained for 90 days by default. Override `auditMaxFileBytes` and
`auditRetentionDays` beneath `security` where local retention obligations differ.

### Password Hash Example

Generate a hash on a trusted command line, then place only the resulting hash in `cms/config.local.php`:

```bash
php -r 'echo password_hash(trim(fgets(STDIN)), PASSWORD_DEFAULT), PHP_EOL;'
```

There is deliberately no enabled default account.

## Content Features

### Repeatable Blocks

Add `neo-dupe` alongside the configured editable class. The editor provides clone-before, clone-after, and delete controls:

```html
<article class="editable neo-dupe">Repeatable content</article>
```

### Shared Content

Use `data-neo-shared` to identify a global region:

```html
<footer class="editable" data-neo-shared="site-footer">Shared footer content</footer>
```

The Shared Content tool updates every HTML page containing the same key. Publishing a page also captures the current value
of its shared regions.

### Navigation Menus

Menus come from your site's own navigation. The **site scan** finds each page's `<nav>`: a header, banner, or main navigation is
treated as `primary`, a footer or legal navigation as `secondary`, and the block is marked in place with
`data-neo-menu="name"`. The first page that contains a menu supplies its items, and a saved menu is never overwritten.

**Tools > Navigation Menus** (main toolbar) lists the menus. Choose **Edit** to change a menu's display name or items, one per
line:

```text
Home | /
About | /about.html
Team | /team.html | About
```

The optional third value is the label of the parent item. Saving rewrites only the list inside each marked `<nav>` on every
page (other bytes, the `<nav>` attributes, and `aria-current="page"` for the current page are handled), and skips pages that
would not change. **Delete** removes a menu from the CMS; pages keep their current navigation. **Scan site for new navigation**
finds navigation blocks a developer has added since the last scan.

The CMS does not create menus: new menus are the web developer's markup, discovered by the scan. The menu key cannot be renamed,
but the display name can.

### Media Library

**Media** uploads and lists files in four categories, decided by the file extension:

| Category | Accepted extensions |
| --- | --- |
| Imagery | jpg, jpeg, png, gif, webp |
| Documents | pdf, docx, xlsx, pptx, odt, ods, odp, txt, csv |
| Video | mp4, m4v, webm, mov, ogv |
| Audio | mp3, wav, ogg, oga, m4a, aac, flac |

Anything not in this list is refused: executables (`.exe`, `.dll`, `.bat`, `.msi`, ...), scripts and server code (`.php`, `.js`, `.sh`, ...),
HTML, SVG, XML, archives, the older `.doc`/`.xls`/`.ppt` formats, and macro-enabled Office files. Every upload is also checked
by content, not just name: the file type must match its extension; files that start like an executable or contain `<?php`,
`<?=`, `<script`, or `<%` anywhere are refused (so a code payload hidden inside an image is caught); PDFs containing
JavaScript, launch actions, or embedded files are refused; and Office/OpenDocument files are refused if they contain macros,
embedded objects, scripts, or executables. Names such as `report.exe.pdf` are refused too. These checks are best-effort hardening,
not a malware scanner, so keep server-side virus scanning where policy requires it.

Uploaded files are saved under `uploads/` with a generated name (`price-list-3f2a9c1d.pdf`); the original name is kept as the
default link text. Every file is limited by `uploads.maxFileBytes` (10 MiB by default), and PHP's `upload_max_filesize` and
`post_max_size` must allow at least that. In the editor, use the **Media library** button to insert an image, a document link, a
video player, or an audio player at the cursor. Documents are served as downloads. `uploads/.htaccess` denies executable and markup
extensions; for Nginx use the rule shown under Web Server Security.

### Site Scan

Administrators can open **Tools > Site scan** to analyse the whole site: page and folder counts, pages that can be made
editable, images without alternative text, broken local image/CSS/script references, uploaded files that no page uses, and
missing title, description, and Open Graph tags. Tick the pages and the kinds of change to make, then apply:

* **Content areas**: adds the editable class to `<main>` (or `role="main"`), otherwise to top-level `<article>` or
  text-bearing `<section>` elements. Headers, navigation, footers, asides, and forms are skipped, and `<body>` is never
  tagged. Pages that already contain an editable region are left alone; pages with no safe container are reported for manual
  tagging.
* **Images**: adds `data-neo-image` to images outside editable regions. In the editor, clicking one opens a dialogue to pick
  a Media Library image or change its address and alternative text.
* **Navigation**: marks each `<nav>` (primary/secondary) with `data-neo-menu` and pre-fills Navigation Menus from it.
* **SEO tags**: inserts only the missing title, description, `og:title`, `og:description`, and `og:image` tags, with values
  taken from the page. Existing tags are never changed, and canonical and robots values are reported but not invented.

The scan edits page source directly rather than re-parsing it, so nothing else in a file changes, and a revision of every
changed page is kept in Revision history.

### Drafts and Revisions

Drafts do not alter public HTML. When opening a page with a saved draft, NeoCMS offers to load it. Publishing creates a
revision of the previous public file first. Deleting a page also retains its last revision, and **Dashboard > Deleted pages**
lists pages whose newest revision is "Before delete" and whose file is gone, with a **Recover** button that recreates the page
from that snapshot. Revisions are pruned by the configured retention limits.

### New Pages and Templates

The **New page** section (administrators), under the page list in **Pages**, is a three-step wizard: choose a template, give the page a name, then choose a navigation group
(or none). The filename is generated from the name (`About Us` becomes `about-us.html`, numbered if taken). The page is saved
only as a private draft: no file exists, so it is not visible on the site, until it is first published (Publish Now or a
schedule). Publishing creates the file and adds a link to the chosen menu on every page. Unpublished pages appear in Pages as
"New - unpublished" and can be discarded.

Templates are the files in `cms/templates/` plus any page you choose **Create Template** for in the Pages list. A template page
is used live (later edits to it show in new pages), and the **Delete** action in the template list deletes a template file or
stops using a page as a template (the page is kept).

### Subfolder Installs

The site can live in a subfolder such as `http://localhost/neocms/`. The URL prefix (`basePath`) and the folder that contains
`cms/` (`siteRoot`) are detected automatically, page links stored by menus are prefixed accordingly, and the session cookie path
follows the CMS. Root installs are unchanged.

NeoCMS stores metadata, drafts, scheduled content, and revisions under `cms/data/`. Back up this directory together with
the website. The supplied `.htaccess` denies direct HTTP access on Apache; other web servers should deny `/cms/data/` and
`/cms/logs/` explicitly. Private directories and files are created with owner-only permissions where the operating system
permits it. Configurable quotas bound drafts, schedules, revisions, and uploaded media to reduce disk-exhaustion risk.

### Scheduled Publishing

Due schedules are processed whenever the CMS API is used. For publication without an active CMS user, run the CLI worker
once per minute with cron:

```cron
* * * * * /usr/bin/php /absolute/path/to/site/cms/publish_scheduled.php >/dev/null 2>&1
```

The server timezone controls PHP date handling; the browser converts the selected local time to an absolute timestamp.

## Administration Workflow

1. Open a page from **Pages** (or create one in the **New page** section under the list).
2. Select a region carrying the configured editable class and edit it in TinyMCE. Clicking an image opens the image dialogue.
3. In the editor, click **Save Draft** to keep your change private, **Publish Now** to replace the public HTML after
   creating a revision, or **Schedule Publish** when publication should occur later. **Cancel** discards unapplied edits.
4. Check revisions, accessibility findings, SEO metadata, shared content, and menus through the relevant toolbar dialogues.

The top bar shows the current page path on the left; your name and Log out are at the top right, with the toolbar buttons
(Dashboard, Pages, Media, SEO, Navigation Menus, Tools) and the desktop/tablet/mobile preview icons beneath. Tools holds
Revision history, Accessibility check, Shared content, Site scan, and Users. Dialogue buttons share one style: small
secondary buttons for row actions, larger buttons for actions (Save, Next, Apply), and blue for the primary action.

Save Draft in the content editor (and Apply in the image and SEO dialogues) stores the change as a private draft. It never writes
the public file, which only changes on Publish. This is intentional and prevents an innocent wording change from making an
unscheduled public debut. The page header shows **Draft:** once a draft is saved, and reopening the page offers to load it.

## Page Markup Recommendations

For the best SEO and accessibility results, each page should include one `<h1>`, a non-empty `<title>`, a meta description,
alternative text on every image, descriptive link text, and labels associated with form fields. The checker highlights
common authoring issues but is not a complete accessibility audit.

## Requirements

* PHP 8 running on Linux
* PHP DOM and fileinfo extensions (the fileinfo extension is used to check uploads; the zip extension is not needed)
* PHP sessions enabled
* Write access to `cms/data/`, `cms/logs/`, `uploads/`, and managed HTML pages
* A modern browser with JavaScript enabled

The administration shell loads jQuery 4.0.0 and jQuery UI 1.14.2 from the official jQuery CDN with Subresource Integrity.
TinyMCE 8.6.0 is bundled locally. Installations that must operate without internet access should self-host the two jQuery
assets and update both `cms/index.php` and the administration Content Security Policy.

## Web Server Security

Apache users should allow the supplied `.htaccess` rules. For Nginx, add equivalent rules such as:

```nginx
location ^~ /cms/data/ { deny all; }
location ^~ /cms/logs/ { deny all; }
location ~ ^/cms/(config(?:\.local)?\.php|config\.local\.php\.example|publish_scheduled\.php)$ { deny all; }
location ~ ^/uploads/.*\.(php[0-9]?|phtml|phar|cgi|pl|py|sh|exe|dll|bat|cmd|com|msi|asp|aspx|jsp|jspx|html?|xhtml|shtml|svg|xml|js|jar|swf)$ { deny all; }
```

Also disable directory listings, prevent PHP source download through server misconfiguration, serve the CMS only over
HTTPS, and back up both public HTML and `cms/data/`. NeoCMS sends CSP, anti-framing, no-sniffing, referrer, permissions,
cross-origin isolation, no-store, and HTTPS HSTS headers where appropriate.

Set request-body limits in the web server and PHP as well as in NeoCMS. For the default upload and API limits, a 12 MiB
server limit leaves room for form overhead without letting oversized requests make themselves at home. For example:

```nginx
client_max_body_size 12m;
client_body_timeout 15s;
```

Match `post_max_size` and `upload_max_filesize` to your configured `security.maxRequestBytes` and `uploads.maxFileBytes`.

The page preview iframe is sandboxed with `allow-same-origin` but without `allow-scripts`. This preserves DOM editing while
preventing public page scripts from reaching the parent administration session.

## Testing

Run the dependency-free integration suite with:

```bash
php tests/run.php
```

It exercises authentication hardening, session revocation, login throttling, iframe sandboxing, path containment, content
limits, configurable editable regions, drafts, publication, revisions, page operations, new-page privacy and templates,
shared content, menu detection and propagation, user accounts (roles, invitations, blocking, password confirmation, config
accounts), and scheduled publishing in temporary directories. See [Development and testing](docs/DEVELOPMENT.md) for the complete check
list used by this repository.

## Licence

NeoCMS is open-source software licensed under the GNU General Public Licence v3.0 (GPLv3). You are free to use, modify,
and distribute this software under the terms of this licence.

For the complete terms, see [LICENSE](LICENSE).

## Found a Bug?

Report defects and feature requests through the project's GitHub issue tracker.
