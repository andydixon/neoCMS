# Development and Testing

## Source Layout

| Path | Responsibility |
| --- | --- |
| `cms/src/NeoCMS/Authentication.php` | Sessions, credentials, roles, capabilities, and CSRF tokens. |
| `cms/src/NeoCMS/CMSController.php` | API dispatch and all page, draft, revision, schedule, media, menu, and shared-content operations. |
| `cms/src/NeoCMS/FileStore.php` | Atomic JSON metadata storage and managed directories. |
| `cms/src/NeoCMS/LoginRateLimiter.php` | Locked, filesystem-backed login throttling. |
| `cms/src/NeoCMS/Logger.php` | Daily audit logging with field normalisation, size rotation, and retention. |
| `cms/src/NeoCMS/SecurityHeaders.php` | CSP, anti-framing, cache, transport, and browser-policy headers. |
| `cms/js/cms.js` | Iframe editing, dialogues, workflow state, and API client. |
| `cms/index.php` | Authenticated administration shell. |
| `cms/image_upload.php` | Multipart image upload endpoint. |
| `cms/publish_scheduled.php` | CLI scheduled-publication worker. |
| `tests/run.php` | Isolated integration suite. |

TinyMCE 8.6.0 beneath `cms/tinymce/` is vendored third-party code and should not be reformatted or documented as first-party
source. Its package metadata, changelog, and licence information remain in that directory.

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
