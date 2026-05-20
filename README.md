# Greyshades Innovations — Enterprise Media Platform

A secure, enterprise-grade Digital Asset Management (DAM) and protected media
streaming platform for **Greyshades Innovations Pvt. Ltd.** Built with PHP 8.2+,
MySQL 8 (or MariaDB 10.5+), HTML, CSS and vanilla JavaScript — no Composer or
Node toolchain required.

This branch contains **Phase 1**: the production-ready foundation. Phases 2 and
3 are scaffolded and clearly marked in the roadmap below.

---

## Table of contents

1. [Highlights](#highlights)
2. [Tech stack](#tech-stack)
3. [Project structure](#project-structure)
4. [Local setup](#local-setup)
5. [Production setup (Apache / Nginx)](#production-setup)
6. [Database schema](#database-schema)
7. [Roles & permissions](#roles--permissions)
8. [Media architecture (no duplication)](#media-architecture)
9. [Secure streaming flow](#secure-streaming-flow)
10. [Watermark & anti-leak protections](#watermark--anti-leak-protections)
11. [Activity & audit logging](#activity--audit-logging)
12. [Phase 2 & 3 roadmap](#roadmap)

---

## Highlights

- Two top-level sections — **Greyshades Graphics** and **Greyshades Events** — with section-level access control. Users without the relevant flag never see restricted areas.
- Nested categories (`Art → Videos → Invitation Videos`, `Art → LBLs → Scientific`, etc.) and free-form metadata (occasions, tags) so a single physical file can appear in many "places" without ever being duplicated.
- SHA-256 file dedup at upload — re-uploading the same file links to the existing record.
- Token-gated streaming: real file paths never appear in any URL.
- HLS adaptive streaming (240p / 480p / 720p / 1080p) with HTTP Range fallback to MP4 when FFmpeg is unavailable.
- Per-user dynamic watermark (name · email · session · timestamp) drifts continuously across protected stages.
- Audit log of every login, upload, view, edit, delete, download, plus per-IP failed-login throttling.
- Modern glass UI with dark/light themes, Pinterest/Netflix-style grid, fully responsive.

---

## Tech stack

| Layer        | Choice                                                  |
|--------------|---------------------------------------------------------|
| Backend      | Core PHP 8.2+ with a small MVC of our own (no framework) |
| Database     | MySQL 8 / MariaDB 10.5+ (managed via phpMyAdmin)         |
| Frontend     | HTML5 + CSS3 (no preprocessor) + vanilla JS             |
| Video        | [hls.js](https://github.com/video-dev/hls.js) loaded via CDN |
| Transcoding  | FFmpeg (videos), ImageMagick (PDF / image), LibreOffice (PPT → PDF) |
| Web server   | Apache (with `.htaccess`) or Nginx                       |

---

## Project structure

```
/
├── public/                  ← document root (everything else is private)
│   ├── index.php            ← front controller
│   ├── .htaccess            ← rewrite to index.php + security headers
│   └── assets/
│       ├── css/app.css
│       └── js/{app,upload,player,watermark,security}.js
├── app/
│   ├── bootstrap.php        ← autoloader, sessions, CSP, error handling
│   ├── routes.php           ← route table
│   ├── Core/                ← Database, Auth, Router, Csrf, View, ActivityLog, StreamToken, Middleware/
│   ├── Controllers/         ← Auth, Dashboard, Media, Upload, Stream, Admin
│   ├── Models/              ← User, Media, Category, Occasion, Tag, Section
│   ├── Services/            ← MediaProcessor (FFmpeg / ImageMagick / LibreOffice wrappers)
│   └── Views/               ← layouts, dashboard, media, upload, admin, errors, auth
├── config/
│   └── config.php           ← reads .env, exposes config('...')
├── database/
│   ├── schema.sql           ← all tables + indexes + FKs
│   └── seed.sql             ← roles, permissions, default super admin, full categories + occasions
├── storage/                 ← PRIVATE (Apache: Require all denied)
│   ├── uploads/
│   │   ├── originals/       ← never served directly
│   │   ├── thumbnails/
│   │   ├── pdf-previews/
│   │   ├── ppt-previews/
│   │   └── hls/             ← HLS bundles per video UUID
│   ├── logs/
│   └── cache/
├── .env.example
└── README.md
```

The `.htaccess` at the project root forwards every request to `/public/`, so
even if a user points the document root at the project root, nothing under
`/storage`, `/app`, `/config` or `/database` is reachable from the web.

---

## Local setup

### 1. Clone and configure

```bash
git clone https://github.com/geryshadesinnovations/greyshades-products.git
cd greyshades-products
cp .env.example .env
# Edit .env - set DB_USER, DB_PASS, APP_KEY, STORAGE_PATH (optional), etc.
```

### 2. Create the database via phpMyAdmin

1. Open phpMyAdmin → **Import** → upload `database/schema.sql` → Go.
2. Open phpMyAdmin → select `greyshades_media` → **Import** → upload `database/seed.sql` → Go.

This creates the schema and seeds:

- 4 roles, 11 permissions, role↔permission mapping
- Both sections (Graphics, Events) with full nested category trees
- ~67 occasions across 3 groups (medical, national, environment)
- A default super admin

> **Default super admin** &nbsp; `admin@greyshades.local` &nbsp;/&nbsp; `Admin@12345`
> **Change this immediately after first login.**

### 3. Required system tools

```bash
# Debian / Ubuntu
sudo apt-get install -y php8.2 php8.2-mysql php8.2-mbstring php8.2-fileinfo \
                        ffmpeg imagemagick libreoffice
```

The platform runs without these — uploads still succeed — but auto previews,
HLS adaptive streaming and PPT thumbnails will be skipped.

### 4. Run

```bash
php -S localhost:8080 -t public public/index.php
```

Visit <http://localhost:8080>, sign in, and start uploading.

---

## Production setup

### Apache (recommended)

```apache
<VirtualHost *:443>
    ServerName media.greyshades.example
    DocumentRoot /var/www/greyshades/public

    <Directory /var/www/greyshades/public>
        AllowOverride All
        Require all granted
    </Directory>

    # Defence in depth - storage is also denied via storage/.htaccess
    <Directory /var/www/greyshades/storage>
        Require all denied
    </Directory>

    SSLEngine on
    SSLCertificateFile      /etc/letsencrypt/live/.../fullchain.pem
    SSLCertificateKeyFile   /etc/letsencrypt/live/.../privkey.pem
</VirtualHost>
```

### Nginx

```nginx
server {
    listen 443 ssl http2;
    server_name media.greyshades.example;
    root /var/www/greyshades/public;
    index index.php;

    location ~ ^/(storage|app|config|database)/ { return 403; }

    location / { try_files $uri $uri/ /index.php?$query_string; }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    }
}
```

Set `php.ini` `upload_max_filesize` and `post_max_size` to at least the value of
`UPLOAD_MAX_MB` from your `.env`.

---

## Database schema

The schema lives in `database/schema.sql`. The key idea is that **media is
stored once** and joined to many concepts:

```
media (uuid, file_path, file_hash, ...)
  ├──< media_categories >── categories (nested via parent_id)
  ├──< media_occasions  >── occasions (grouped by occasion_groups)
  └──< media_tags       >── tags
```

Notable indexes:

- `media (file_hash)` UNIQUE — dedup detection
- `media (uuid)` UNIQUE — public identifier
- `media FULLTEXT (title, description, keywords)` — fast search
- `activity_logs (created_at)`, `(user_id)`, `(entity_type, entity_id)` — audit queries
- `categories (section_id, parent_id)` — tree traversal

Operational tables: `activity_logs`, `download_logs`, `view_logs`,
`user_sessions`, `stream_tokens`, `failed_logins`.

---

## Roles & permissions

| Role          | Code           | Default access                                |
|---------------|----------------|-----------------------------------------------|
| Super Admin   | `super_admin`  | All sections, all permissions, manages users  |
| Graphics User | `graphics_user`| Greyshades Graphics, view only                |
| Events User   | `events_user`  | Greyshades Events, view only                  |
| Combined User | `combined_user`| Both sections, view only                      |

Role gives the **floor**; the per-user boolean flags in `users`
(`can_upload`, `can_edit`, `can_delete`, `can_download`, `can_manage_users`,
`can_graphics`, `can_events`) give granular overrides. Super admins get
everything regardless of flags.

---

## Media architecture

Each upload:

1. SHA-256 hash → `media.file_hash` UNIQUE — dedup short-circuit returns the existing UUID.
2. Random UUID v4 → `media.uuid` (only public identifier).
3. Original written to `storage/uploads/originals/YYYY/MM/<uuid>.<ext>`.
4. Type-specific preview generated:
   - video → 1-second JPEG thumbnail + duration via FFprobe + (best-effort) HLS bundle in `storage/uploads/hls/<uuid>/`
   - PDF → first-page PNG via ImageMagick
   - PPT/PPTX → LibreOffice converts to PDF, then ImageMagick takes first-page PNG
   - image → 600px-max optimised JPEG via ImageMagick (with copy fallback)
5. Categories / occasions / tags attached via junction tables — **never copies the file**.

The same file therefore appears in:
- the section it belongs to,
- every category and ancestor category it is tagged with,
- every occasion the upload form selects,
- every tag,
- and any search/sort that matches its title / description / keywords.

---

## Secure streaming flow

```
Browser  ──GET /media/{uuid}─────►  MediaController::show
                                    ├─ verify section access
                                    ├─ bump view_count + view_logs row
                                    └─ issue stream_tokens row (TTL 15 min)
                                       returns short-lived token

Browser  ──GET /stream/{uuid}?token=...►  StreamController::stream
                                          ├─ verify session + section
                                          ├─ verify token still alive
                                          └─ stream file with HTTP Range (206)

Browser  ──GET /stream/{uuid}/hls/master.m3u8?token=...►  StreamController::hls
                                                          ├─ same auth
                                                          ├─ basename() guard against path traversal
                                                          └─ stream the playlist or .ts segment
```

- Tokens are single-purpose (`user_id` + `media_id`) and stored in the DB so
  logout immediately revokes every active stream URL via
  `StreamToken::revokeForUser`.
- `download/{uuid}` is gated by `users.can_download` OR a row in
  `media_download_grants` for the file/user, plus `media.is_downloadable` and
  `download_expiry`. Every byte sent is logged to `download_logs` with IP,
  user-agent and session.

---

## Watermark & anti-leak protections

The platform takes a **defence-in-depth** approach. None of these alone is
unbreakable; together they raise the cost of leaks substantially and make any
captured frame traceable to the exact viewer.

- **Dynamic watermark**: `public/assets/js/watermark.js` paints a tiled SVG
  across the viewport with the user's name, email, short session ID and a
  live timestamp, drifting horizontally every 1.5 s. Restored automatically
  if hidden via DOM tampering.
- **Right-click / drag**: blocked on `<img>`, `<video>`, and any element
  inside `.media-stage` or `.no-select`.
- **Dev-tool shortcuts**: F12, Ctrl+Shift+I/J/C/K, Ctrl+U/S/P intercepted.
- **Visibility blur**: when the tab loses focus, all media stages get a
  20px blur — useful against opportunistic over-the-shoulder captures.
- **CSP**: tight Content-Security-Policy headers in `app/bootstrap.php`.
- **Print kill switch**: `@media print { body { display: none !important } }`.
- **Storage**: every directory under `/storage` ships with an `.htaccess`
  containing `Require all denied`. Files are only ever served by PHP.
- **PDF viewer hardening**: rendered in an iframe with
  `#toolbar=0&navpanes=0&scrollbar=0` and `sandbox="allow-scripts allow-same-origin"`
  (download/print buttons are hidden by the browser).

---

## Activity & audit logging

Every meaningful action lands in `activity_logs` with user, action, entity,
metadata, IP, user agent and session ID. Failed logins are throttled at
5 attempts per IP per 15 minutes (`failed_logins`).

Specialised log tables:

- `view_logs` — every media view (with optional duration & quality for Phase 2)
- `download_logs` — bytes_sent + OS + browser
- `user_sessions` — live sessions with `current_media_id` so admins can see
  exactly what each online user is watching right now

Surfaced in **Admin → Overview** and **Admin → Activity log**.

---

## Roadmap

### Phase 1 — Foundation **(this branch)**

- [x] PHP MVC framework, autoloader, CSP, sessions
- [x] PDO Database layer with prepared statements
- [x] Full schema + seed data + super admin
- [x] Auth + RBAC + per-user permission flags + login throttling
- [x] Section-aware dashboard with nested-tree sidebar, filter bar, search, pagination
- [x] Drag-and-drop upload with progress + SHA-256 dedup
- [x] Auto thumbnails for images / videos / PDFs / PPTs (FFmpeg / ImageMagick / LibreOffice)
- [x] Token-gated streaming endpoint with HTTP Range support
- [x] Best-effort HLS transcoding with hls.js player + Safari native fallback
- [x] Permission-gated downloads with full audit trail
- [x] Dynamic watermark + browser anti-inspect deterrents
- [x] Admin: users CRUD, categories CRUD, activity log, live sessions

### Phase 2 — Streaming, analytics & granular permissions

- [ ] Move HLS transcoding off the upload request into a job queue
      (`storage/cache/jobs/*` + a worker invoked via cron `php cli.php work`)
- [ ] Encrypted HLS keys served via the same token endpoint (AES-128 key rotation)
- [ ] Detailed video watch analytics: duration, % completed, pause/resume,
      buffering metrics — JS hook already stubbed in `player.js`
- [ ] Per-file `media_download_grants` admin UI
- [ ] Bulk operations (multi-select cards → bulk tag / category / delete)
- [ ] Resumable / chunked uploads (tus.io style) for files > 1 GB
- [ ] Search → DataTables-style infinite scroll instead of paged
- [ ] Live "currently watching" admin board with WebSockets / SSE
- [ ] Cropper.js custom thumbnail upload

### Phase 3 — Enterprise polish

- [ ] AI auto-tagging (vision API / OCR for PDFs)
- [ ] Speech-to-text indexing for videos
- [ ] Duplicate detection by perceptual hash, not just SHA-256
- [ ] DRM-grade encrypted streaming (Widevine / FairPlay) for highest-tier content
- [ ] SAML / OIDC SSO
- [ ] Multilingual UI (i18n)
- [ ] Cloud-storage migration (S3 / Backblaze) with the same PHP-controlled access layer
- [ ] Mobile companion app via the existing routes
- [ ] Automated DB & storage backup with restore wizard

---

## License & ownership

Internal project for **Greyshades Innovations Pvt. Ltd.** All rights reserved.
