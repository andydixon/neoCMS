# Development and Testing

## Source Layout

| Path | Responsibility |
| --- | --- |
| `cms/src/NeoCMS/Authentication.php` | Sessions, credentials, roles, capabilities, and CSRF tokens. |
| `cms/src/NeoCMS/CMSController.php` | API dispatch and the page, draft, revision, schedule, media, menu, and shared-content actions. |
| `cms/src/NeoCMS/FileStore.php` | Atomic JSON metadata storage, locked `update()` read-modify-write, and managed directories. |
| `cms/src/NeoCMS/UserStore.php` | Accounts: locked config accounts plus CMS accounts in `data/users.json`, invitations, and the password policy. |
| `cms/src/NeoCMS/MediaTypes.php` | Allowed media extensions and categories, and the upload safety checks (`inspect()`). |
| `cms/src/NeoCMS/Activity.php` | Records an event in both the dashboard feed and the audit log. |
| `cms/src/NeoCMS/PagePaths.php` | URI normalisation and containment of page paths inside the document root. |
| `cms/src/NeoCMS/SiteAnalyser.php` | Byte-exact analysis and tagging of content regions, images, and SEO tags for the Site scan. |
| `cms/src/NeoCMS/ContentDom.php` | Pure HTML transforms for shared regions, menus, and editable-class detection. |
| `cms/bootstrap.php` | Shared entry-point setup: configuration and autoloader. |
| `cms/src/NeoCMS/LoginRateLimiter.php` | Locked, filesystem-backed login throttling. |
| `cms/src/NeoCMS/Logger.php` | Daily audit logging with field normalisation, size rotation, and retention. |
| `cms/src/NeoCMS/SecurityHeaders.php` | CSP, anti-framing, cache, transport, and browser-policy headers. |
| `cms/js/cms.js` | Iframe editing, dialogues, workflow state, and API client. |
| `cms/index.php` | Authenticated administration shell. |
| `cms/image_upload.php` | Multipart media upload endpoint (images, documents, video, audio); also used by TinyMCE for pasted images. |
| `cms/publish_scheduled.php` | CLI scheduled-publication worker. |
| `tests/run.php` | Isolated integration suite. |

TinyMCE 8.6.0 beneath `cms/tinymce/` is vendored third-party code and should not be reformatted or documented as first-party
source. Its package metadata, changelog, and licence information remain in that directory.

Every read-modify-write of a JSON document must go through `FileStore::update()`; plain `read()` then `write()` loses concurrent updates.

## Documentation Style

Comments use British English and explain intent, constraints, side effects, and security decisions. They should not narrate
obvious syntax. A comment saying that a variable is assigned adds little; a comment explaining why a temporary file is
renamed atomically earns its tea.

Public and non-obvious private methods use docblocks. Browser workflows use JSDoc-style comments. CSS and HTML comments
describe logical regions rather than every individual declaration or tag.

## Required Checks

Run the integration suite:

```bash
php tests/run.php
```

Lint all first-party PHP files:

```bash
find cms tests -path 'cms/tinymce' -prune -o -name '*.php' -print0 | xargs -0 -n1 php -l
```

Parse-check the administration client:

```bash
node --check cms/js/cms.js
```

Check patch whitespace before committing:

```bash
git diff --check
```

## Adding an API Action

1. Add a private method named `<action>Action()` to `CMSController`.
2. Use `requirePost('<capability>')` before every mutation.
3. Obtain required strings through `requiredPost()` or `requiredRequest()`.
4. Constrain paths through the existing URI and page-path helpers.
5. Record meaningful mutations with `activity()` and create revisions before replacing public content.
6. Return data through `respond()` so headers and JSON encoding remain consistent.
7. Add an integration assertion to `tests/run.php`.
8. Document user-visible behaviour in `README.md` and `cms/welcome.html` when appropriate.

Mutating actions that accept full documents must use `requiredContentPost()` rather than reading `$_POST` directly. New
filesystem operations must reuse canonical path helpers, reject symbolic links, and respect the configured count and byte
ceilings. New HTML entry points should use `SecurityHeaders` before emitting output.

## Filesystem Tests

The integration suite creates unique public and metadata directories under the operating system's temporary directory. It
seeds an authenticated administrator session, invokes controller actions directly, and cleans up in a `finally` block.
Tests must never rely on or modify the repository's public HTML, uploads, logs, or data files.

## Local Testing on XAMPP (Windows)

Place the project in XAMPP's `htdocs` folder (for example `htdocs/neocms/`) and browse to `http://localhost/neocms/cms/`. No virtual host is
needed: NeoCMS detects the `/neocms` URL prefix from the request and uses the folder containing `cms/` as the site root. On a
live site at the domain root the prefix is empty and behaviour is unchanged. If a proxy hides the real path, set `'basePath'`
in `cms/config.local.php`.

1. Copy `cms/config.local.php.example` to `cms/config.local.php`.
2. Generate a hash (use XAMPP's `php.exe` if `php` is not on your PATH): `php -r "echo password_hash('choose-a-password', PASSWORD_DEFAULT);"`.
3. Add the hash under `authentication` and a role under `roles`, then browse to `/cms/`. This config account is the main administrator; further accounts are added in Tools > Users.

Windows ignores `chmod`, so private-file permissions are not enforced there; the permission assertions in `tests/run.php`
run only on Linux. Keep `.htaccess` enabled (`AllowOverride All`) so `cms/data/` and `cms/logs/` stay private.

## Production Checklist (Linux + Apache)

- Serve over HTTPS only. Behind a TLS-terminating proxy set `'security' => ['cookieSecure' => true]` in `config.local.php`.
- Create `cms/config.local.php` with password hashes for the main administrator(s) (`editor` or `administrator` roles); never commit it. Other accounts are created in Tools > Users.
- Set `'dataDirectory'` in `config.local.php` to an absolute folder **outside the web root** (for example `/var/neocms-data`, writable by PHP). Accounts, drafts and revisions then can never be served. Administrators see a Dashboard recommendation until this is done.
- Copy `uploads/.htaccess` with the site: it denies executable and markup extensions and serves documents as downloads. On Nginx add the `/uploads/` rule from the README.
- Enable `AllowOverride All` for the site so the shipped `.htaccess` files apply, and confirm `mod_headers` is loaded.
- Own the writable paths (`cms/data/`, `cms/logs/`, `uploads/`, and managed HTML) by the PHP user, for example `www-data`.
- Set PHP `post_max_size` and `upload_max_filesize` to at least `uploads.maxFileBytes` (10 MiB by default; raise both together for video). The PHP `fileinfo` extension is required for upload checks; the `zip` extension is not.
- Add the cron entry from the README for `cms/publish_scheduled.php`.
- The admin loads jQuery from `code.jquery.com`; self-host it and update `cms/index.php` and the CSP if the server must work offline.
- Back up `cms/data/` together with the public HTML.
- Verify after deploying: `/cms/data/`, `/cms/logs/`, `/cms/config.php`, and any `.php` or `.exe` placed in `/uploads/` return 403; the session cookie is `Secure`;
  five wrong passwords trigger throttling; `php tests/run.php` passes on the server.
- Upload `cms/`, `uploads/`, and the HTML to the site root. Nothing needs changing from the XAMPP setup. Links written as root-relative (`/about.html`) resolve under `/neocms/` only on the live site, so use relative links or test them after deployment.