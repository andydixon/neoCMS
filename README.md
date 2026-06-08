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
* Drafts and Publishing: Editors can save drafts without changing the public page. Publishers can publish immediately or
  schedule a publication.
* Revision History: Every changed or deleted page receives a timestamped filesystem snapshot that publishers can restore.
* Page Management: Search, create, duplicate, rename, and delete editable HTML pages, including pages in subdirectories.
* Media Library: Browse uploaded images, reuse them, maintain alternative text, see usage counts, and remove files.
* SEO Settings: Edit the page title, description, canonical URL, Open Graph image, and robots indexing setting.
* Shared Content: Mark content with `data-neo-shared="name"` and update matching regions across the site.
* Navigation Menus: Build ordered and nested navigation markup and insert it into an editable region.
* Responsive Preview: Preview the current page at desktop, tablet, and mobile widths.
* Accessibility Checks: Check common authoring problems such as missing alt text, empty links, heading jumps, missing labels,
  and absent page metadata before publishing.
* Roles and Activity: Use editor, publisher, and administrator roles. The dashboard displays drafts, schedules, system
  problems, and recent activity.

## Installation Guide

To install NeoCMS:

* Place the `cms/` directory in the document root of the static website.
* Place the `uploads/` directory in the same document root.
* Copy `cms/config.local.php.example` to `cms/config.local.php`, add password hashes, and assign roles.
* Update the elements you want to make editable by adding the configured editable class. It is `editable` by default.
* Confirm that PHP may write to the managed HTML files, `cms/data/`, `cms/logs/`, and `uploads/`.
* Visit `/cms/`, log in, and begin editing.

That’s it! You’re up and running.

## Configuration

Tracked defaults live in `cms/config.php`. Deployment credentials and overrides belong in the Git-ignored
`cms/config.local.php`, which must return an array. The supplied Apache rules deny direct access to both files.

| Setting | Type | Purpose |
| --- | --- | --- |
| `authentication` | array | Maps usernames to `password_hash()` values; plaintext credentials are rejected. |
| `roles` | array | Maps usernames to `editor`, `publisher`, or `administrator`. |
| `security` | array | Configures session expiry, login throttling, request limits, storage quotas, and audit-log retention. |
| `uploads` | array | Configures image size, dimension, file-count, and aggregate-storage limits. |
| `audit` | boolean | Enables daily activity logs under `cms/logs/`. |
| `skipWelcomePage` | boolean | Opens the public root instead of the built-in guide after login. |
| `showFullUrl` | boolean | Displays the current page path in the toolbar. |
| `editableClass` | string | Selects the single CSS class used to identify editable regions. |

`dataDirectory` is an internal override used by the integration suite and specialised deployments. Normal installations
should allow NeoCMS to use `cms/data/`.

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

Passwords must be generated with `password_hash()` and stored under `authentication` in `cms/config.local.php`. Assign the
same username a role under `roles`:

```php
'authentication' => [
    'author' => '$2y$10$...',
    'publisher' => '$2y$10$...',
],
'roles' => [
    'author' => 'editor',
    'publisher' => 'publisher',
],
```

* `editor`: edit content, save drafts, and upload media.
* `publisher`: editor permissions plus immediate and scheduled publishing and revision restoration.
* `administrator`: publisher permissions plus page, media, shared-content, and menu management.

Users without an explicit valid role receive `editor`, the least powerful role. Account removal, password-hash changes,
and role changes affect active sessions on their next request. Sessions expire after 30 minutes of inactivity and 12 hours
in total by default.

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

Menu items use one line each in this format:

```text
Home | /
About | /about.html
Team | /team.html | About
```

The optional third value is the label of the parent item. After saving, the generated `<nav>` can be inserted into the
currently selected editable region.

### Drafts and Revisions

Drafts do not alter public HTML. When opening a page with a saved draft, NeoCMS offers to load it. Publishing creates a
revision of the previous public file first. Deleting a page also retains its last revision.

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

1. Open a page from **Pages**.
2. Select a region carrying the configured editable class and edit it in TinyMCE.
3. Use **Save Draft** to retain private work, or **Publish** to replace the public HTML after creating a revision.
4. Use **Schedule** when publication should occur later.
5. Check revisions, accessibility findings, SEO metadata, shared content, and menus through the relevant toolbar dialogues.

The Save button inside the content-editor modal only applies content to the in-browser preview. It does not write a public
file. This distinction is intentional and prevents an innocent wording change from making an unscheduled public debut.

## Page Markup Recommendations

For the best SEO and accessibility results, each page should include one `<h1>`, a non-empty `<title>`, a meta description,
alternative text on every image, descriptive link text, and labels associated with form fields. The checker highlights
common authoring issues but is not a complete accessibility audit.

## Requirements

* PHP 8 running on Linux
* PHP DOM and fileinfo extensions
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
location ~ ^/uploads/.*\.(php[0-9]?|phtml|phar|cgi|pl|py|sh)$ { deny all; }
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
limits, configurable editable regions, drafts, publication, revisions, page operations, shared content, menus, and
scheduled publishing in temporary directories. See [Development and testing](docs/DEVELOPMENT.md) for the complete check
list used by this repository.

## Licence

NeoCMS is open-source software licensed under the GNU General Public Licence v3.0 (GPLv3). You are free to use, modify,
and distribute this software under the terms of this licence.

For the complete terms, see [LICENSE](LICENSE).

## Found a Bug?

Report defects and feature requests through the project's GitHub issue tracker.
