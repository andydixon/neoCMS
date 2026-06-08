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

`CMSController` is the single API dispatcher. It authenticates every request and delegates mutating actions through
`requirePost()`, which enforces POST, CSRF validation, and the requested role capability.

Image uploads use the separate `cms/image_upload.php` endpoint because TinyMCE sends multipart file data. It applies the
same authentication, role, and CSRF requirements before validating image bytes.

## Storage Model

Published content stays in the web document root. NeoCMS stores supporting data beneath `cms/data/`:

| Path or document | Contents |
| --- | --- |
| `drafts/` | Complete unpublished HTML documents, named by a hash of their URI. |
| `revisions/` | Immutable snapshots of earlier published documents. |
| `scheduled/` | Complete HTML staged for future publication. |
| `drafts.json` | Human-readable draft ownership and timestamp metadata. |
| `revisions.json` | Revision URI, author, reason, and timestamp index. |
| `schedules.json` | Publication times and staged-document identifiers. |
| `shared.json` | Named global HTML fragments. |
| `menus.json` | Named flat menu structures with optional parent labels. |
| `media.json` | Reusable image alternative text. |
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
marks generated navigation that should be replaced whenever its named menu changes.

The bundled templates use the default `editable` class. Sites that override `editableClass` must update their templates
to match; otherwise the resulting page is valid HTML but, quite reasonably, is not treated as editable by NeoCMS.

## Roles

Capabilities are intentionally small and cumulative:

| Role | Capabilities |
| --- | --- |
| `editor` | Save drafts and upload images. |
| `publisher` | Editor capabilities plus publish, schedule, and restore revisions. |
| `administrator` | Publisher capabilities plus page, media, shared-content, and menu management. |

The interface hides controls the user cannot invoke, but server-side checks remain authoritative. Hidden buttons are a
convenience, not a security boundary wearing a clever disguise.

Active sessions are bound to the current configured password hash. Removing an account or changing its hash invalidates
the session on the next request, while roles are re-read from configuration so demotion is immediate.

## Scheduled Publishing

Every API request opportunistically processes due jobs. Reliable unattended operation comes from invoking
`cms/publish_scheduled.php` through cron. The worker is CLI-only and uses the same publication and revision path as the UI.

## Security Boundaries

NeoCMS relies on PHP sessions with strict mode, HTTP-only cookies, SameSite=Lax, and Secure cookies under HTTPS. Mutating
requests require a constant-time-checked CSRF token. Page paths are canonicalised and constrained to HTML files beneath the
document root, excluding the CMS directory and symbolic links. Uploads are constrained by byte size, detected MIME type,
image decoding, dimensions, pixel count, file count, and total storage. Login attempts are throttled by hashed address and
address/username buckets.

HTML and JSON responses receive Content Security Policy, anti-framing, no-sniffing, referrer, permissions, cross-origin,
cache-control, and conditional HSTS headers. CDN scripts and styles are fixed to explicit versions and protected by SRI.

The supplied Apache rules protect configuration, data, logs, CLI entry points, and uploaded executable extensions. Other
servers must provide equivalent denial rules, and production use should always use HTTPS.

Audit entries are locked while appending, normalised to prevent forged lines, rotated at a configurable size, and pruned
after a configurable retention period. They remain operational records rather than a tamper-proof ledger.
