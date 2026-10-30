# CompHub Native — Implementation Plan

> **For the executing agent:** implement task by task, in order. Each task ends
> with focused proof. Do not start a later task while an earlier gate is red.
> The approved design at
> `docs/superpowers/specs/2026-10-28-comphub-native-design.md` is authoritative.
> This plan adds execution detail; it does not replace the design.

**Goal:** rebuild the Laravel application in `../revenge/` as a dependency-free
PHP 8.3 application in this directory, with the same user-facing features,
verified real event data only, a hard guest gate on external registration
links, and a deliberate glassmorphism UI.

**Architecture:** one front controller, a small in-repo framework under
`src/Core`, PDO repositories, service-layer business rules, plain PHP views,
file sessions, file cache, log mailer. No Composer, no ORM, no Docker, no npm.

**Tech:** PHP 8.3+ (`pdo_pgsql`, `mbstring`, `curl`, `dom`, `fileinfo`,
`openssl`), PostgreSQL 14+, PostgreSQL schema `lomba_kampus_native` and
`lomba_kampus_native_test`.

**Source of truth for parity:** `../revenge/routes/web.php`,
`../revenge/app/Services/Moderation/`, `../revenge/config/moderation.php`,
`../revenge/database/migrations/`, and the 24 tests under `../revenge/tests/`.

**Hard constraints**

- `../revenge/` is read-only reference. Never edit it.
- No demo users, events, reports, bookmarks, or passwords.
- No unverified event. Every curated event has `source_url`,
  `source_checked_at`, and the original organizer name.
- A guest response must not contain `registration_url` or `source_url` in
  HTML, metadata, JSON, data attributes, or redirects. Source domain text is
  allowed; the URL itself is not.
- Seven moderation rules only: `BlockedKeyword`, `ContactLeak`,
  `ContentQuality`, `DateSanity`, `DuplicateEvent`, `RateLimit`,
  `SuspiciousUrl`.
- No automatic approval by the moderation engine. Admin decision is final.
- No `git commit`, `git push`, or PR unless the user explicitly asks. Treat
  each task boundary as a review checkpoint instead.

---

## 1. File map

```
revenge-native/
  public/index.php
  public/.htaccess                 # optional Apache front-controller rewrite
  public/assets/css/app.css
  public/assets/js/app.js
  public/assets/img/               # only real, local assets
  bootstrap/app.php                # build container, config, routes
  bootstrap/helpers.php            # e(), url(), csrf_field(), auth_user(), ...
  config/app.php
  config/moderation.php
  config/security.php
  routes/web.php
  src/Core/Autoloader.php
  src/Core/Env.php
  src/Core/Config.php
  src/Core/Container.php
  src/Core/App.php
  src/Core/Request.php
  src/Core/Response.php
  src/Core/Route.php
  src/Core/Router.php
  src/Core/View.php
  src/Core/Session.php
  src/Core/Csrf.php
  src/Core/Auth.php
  src/Core/Database.php
  src/Core/Validator.php
  src/Core/ValidationException.php
  src/Core/RateLimiter.php
  src/Core/FileCache.php
  src/Core/Mailer.php
  src/Core/LogMailer.php
  src/Core/Paginator.php
  src/Core/HttpException.php
  src/Http/Controllers/Controller.php
  src/Http/Controllers/HomeController.php
  src/Http/Controllers/EventCatalogController.php
  src/Http/Controllers/BookmarkController.php
  src/Http/Controllers/ReportController.php
  src/Http/Controllers/ProviderDirectoryController.php
  src/Http/Controllers/StaticPageController.php
  src/Http/Controllers/AuthController.php
  src/Http/Controllers/PasswordResetController.php
  src/Http/Controllers/UserDashboardController.php
  src/Http/Controllers/ProfileController.php
  src/Http/Controllers/ProviderDashboardController.php
  src/Http/Controllers/ProviderProfileController.php
  src/Http/Controllers/ProviderEventController.php
  src/Http/Controllers/Admin/AdminDashboardController.php
  src/Http/Controllers/Admin/EventModerationController.php
  src/Http/Controllers/Admin/ProviderVerificationController.php
  src/Http/Controllers/Admin/ReportReviewController.php
  src/Http/Controllers/Admin/UserManagementController.php
  src/Http/Controllers/Admin/CategoryController.php
  src/Http/Controllers/Admin/AuditLogController.php
  src/Http/Controllers/Admin/VerificationDocumentController.php
  src/Http/Middleware/Middleware.php
  src/Http/Middleware/MiddlewareRegistry.php
  src/Http/Middleware/AuthMiddleware.php
  src/Http/Middleware/GuestMiddleware.php
  src/Http/Middleware/RoleMiddleware.php
  src/Http/Middleware/ActiveMiddleware.php
  src/Http/Middleware/VerifiedMiddleware.php
  src/Http/Middleware/CsrfMiddleware.php
  src/Http/Middleware/ThrottleMiddleware.php
  src/Http/Middleware/SecurityHeadersMiddleware.php
  src/Http/Requests/FormRequest.php
  src/Http/Requests/Auth/LoginRequest.php
  src/Http/Requests/Auth/RegisterRequest.php
  src/Http/Requests/Auth/ProviderRegistrationRequest.php
  src/Http/Requests/Auth/PasswordResetRequest.php
  src/Http/Requests/Auth/PasswordUpdateRequest.php
  src/Http/Requests/Event/StoreEventRequest.php
  src/Http/Requests/Event/UpdateEventRequest.php
  src/Http/Requests/Profile/UpdateProfileRequest.php
  src/Http/Requests/Profile/ChangePasswordRequest.php
  src/Http/Requests/Profile/UpdateProviderProfileRequest.php
  src/Http/Requests/Report/StoreReportRequest.php
  src/Http/Requests/Admin/ModerateEventRequest.php
  src/Http/Requests/Admin/ProviderDecisionRequest.php
  src/Http/Requests/Admin/ReportDecisionRequest.php
  src/Http/Requests/Admin/UserUpdateRequest.php
  src/Http/Requests/Admin/CategoryRequest.php
  src/Enums/UserRole.php
  src/Enums/AccountStatus.php
  src/Enums/EventStatus.php
  src/Enums/EventMode.php
  src/Enums/VerificationStatus.php
  src/Enums/ReportReason.php
  src/Enums/ReportStatus.php
  src/Enums/ModerationAction.php
  src/Enums/RuleSeverity.php
  src/Models/                        # only value objects with behavior
  src/DataTransferObjects/CatalogFilter.php
  src/Repositories/Contracts/*.php
  src/Repositories/Pdo/*.php
  src/Services/Access.php
  src/Services/AuthService.php
  src/Services/EventService.php
  src/Services/BookmarkService.php
  src/Services/ReportService.php
  src/Services/ProviderVerificationService.php
  src/Services/AuditLogService.php
  src/Services/EventExpiryService.php
  src/Services/EventImporter.php
  src/Services/Moderation/ModerationContext.php
  src/Services/Moderation/ModerationRule.php
  src/Services/Moderation/RuleResult.php
  src/Services/Moderation/ModerationVerdict.php
  src/Services/Moderation/ModerationEngine.php
  src/Services/Moderation/Rules/*.php
  src/Support/DateHelper.php
  src/Support/TextNormalizer.php
  src/Support/SlugGenerator.php
  resources/views/partials/head.php
  resources/views/partials/header.php
  resources/views/partials/footer.php
  resources/views/partials/flash.php
  resources/views/partials/pagination.php
  resources/views/components/*.php
  resources/views/pages/*.php
  resources/views/events/index.php
  resources/views/events/show.php
  resources/views/providers/index.php
  resources/views/providers/show.php
  resources/views/auth/*.php
  resources/views/user/*.php
  resources/views/provider/*.php
  resources/views/admin/*.php
  resources/views/errors/*.php
  database/schema.sql
  database/seed/categories.sql
  data/events/2026-10-28.json
  bin/console
  storage/logs/.gitkeep
  storage/sessions/.gitkeep
  storage/cache/.gitkeep
  storage/uploads/verification/.gitkeep
  tests/bootstrap.php
  tests/run.php
  tests/TestCase.php
  tests/Unit/*Test.php
  tests/Feature/*Test.php
  .env.example
  .env.testing.example
  README.md
```

**Repository return shape.** Repositories return associative arrays with
snake_case keys matching the database. No ORM. Dates stay ISO-8601 strings from
PostgreSQL and are converted by `DateHelper` at the view boundary. Enum columns
are converted to enum instances in repositories, never left as loose strings in
service code.

**Design deviations intentionally rejected:** Eloquent, Blade, Laravel
middleware classes, Carbon, `Str::slug`, facades, service providers, queue
workers, and Livewire. Each has a small native replacement named above.

---

## 2. Shared conventions

- `declare(strict_types=1);` in every PHP file.
- Namespace root `App\` maps to `src/`.
- Public methods have parameter and return types.
- SQL uses named placeholders only. No string interpolation of user values.
- All state-changing operations run in a database transaction.
- All views escape with `e()`. Raw HTML only through an explicit
  `raw()` helper that is never fed user input.
- CSRF token is required on every non-GET request.
- Validation messages are Indonesian and specific.
- Session keys: `auth_user_id`, `auth_remember`, `_csrf`, `_old`, `_errors`,
  `_flash`.
- Timezone: `Asia/Jakarta` for display, UTC storage. `APP_TIMEZONE` controls
  display; database columns are `timestamptz`.
- Pagination: 12 items public, 20 admin.
- Status labels come from enum `label()`; tones from enum `tone()`.
- No hard-coded event or user data outside `data/events/*.json`,
  `database/seed/categories.sql`, and tests.

**Test framework.** `tests/run.php` registers global `test()`, assertions, and a
per-test transaction. Each test prints `PASS`/`FAIL`; non-zero exit on failure.
`tests/bootstrap.php` loads `.env.testing`, sets `APP_ENV=test`, builds the app,
runs `migrate` against the test database, and exposes `app()` and `db()`.
Feature tests call `request($method, $path, $input, $userId)` and inspect the
`Response` without a network. `bin/console test` runs the suite.

**Proof command for every task:** `php bin/console test` plus the task-specific
command. Also run `php -l` on every changed PHP file, or a loop over `src/`,
`resources/views/`, `tests/`, and `bin/console`.

---

## Task 1 — Skeleton, environment, config, front controller

**Files:** `public/index.php`, `public/.htaccess`, `bootstrap/app.php`,
`bootstrap/helpers.php`, `config/app.php`, `config/security.php`,
`src/Core/Autoloader.php`, `src/Core/Env.php`, `src/Core/Config.php`,
`src/Core/HttpException.php`, `.env.example`, `.env.testing.example`,
`storage/*/.gitkeep`.

**Steps**

1. `Autoloader::register(string $srcDir): void` maps `App\` to `$srcDir` and
   throws `RuntimeException` on a missing file.
2. `Env::load(string $path): void` parses `KEY=value`, quoted values, and `#`
   comments without interpolation. `Env::get`, `Env::bool`, `Env::int`,
   `Env::set` (tests only). Never overwrite an already-set non-empty value.
3. `Config::load(string $dir): void`, `Config::get(string $key, mixed $default
   = null): mixed` using dot notation. Config files return arrays and read
   `Env`.
4. `config/app.php`: `name`, `env`, `debug`, `url`, `timezone`
   (`Asia/Jakarta`), `session.cookie`, `session.lifetime`, `mail.driver`.
5. `config/security.php`: password reset TTL 60 minutes, remember cookie name
   and TTL 30 days, upload rules (2 MB, PDF/JPEG/PNG), session cookie flags.
6. Helpers: `e()`, `url()`, `asset()`, `csrf_token()`, `csrf_field()`,
   `old()`, `auth_user()`, `flash()`, `raw()`.
7. `public/index.php` builds the app and sends the response. Catch
   `HttpException`, `ValidationException`, and `Throwable`; render error views
   or JSON. In production, never print stack traces.
8. `.env.example` contains every variable with empty or safe defaults. No
   working password.

**Test:** `tests/Unit/EnvTest.php` covers quoted values, comments, defaults,
and no-overwrite. `tests/Unit/AutoloaderTest.php` loads a real `App\` class.

**Run:** `php -l public/index.php && php bin/console test`

**Gate:** app boots without a database connection.

---

## Task 2 — Container, router, request/response, views, test runner

**Files:** `src/Core/{Container,App,Request,Response,View,Route,Router}.php`,
`src/Http/Controllers/Controller.php`, `routes/web.php`, `tests/bootstrap.php`,
`tests/run.php`, `tests/TestCase.php`, `resources/views/errors/*.php`.

**Steps**

1. `Container::bind/singleton/instance/make`. `make()` uses reflection and
   resolves typed constructor parameters recursively; interfaces must be bound
   explicitly or it throws a clear error.
2. `Request::capture()` reads globals. `Request::create(method, path, input,
   headers, files)` is the test factory. Normalize the path: strip query,
   collapse slashes, reject `..`.
3. `Response` with `html`, `json`, `redirect`, `download`, `send`. Set
   `Content-Type`, `X-Content-Type-Options: nosniff`, `Referrer-Policy:
   strict-origin-when-cross-origin`, and a CSP that allows only self assets.
4. `Route` supports `{param}` and `{param:id}` syntax, optional numeric
   constraint, `name()`, and `middleware()`. `Router::dispatch` matches method
   and path, writes route params to request attributes, resolves `[Class,
   method]` through the container, and returns 404/405 correctly.
5. `Router::url(name, params)` builds paths from the named route table.
6. `View::render(template, data)` extracts data and includes the file. Missing
   templates throw `RuntimeException`. Views use partials, not a template
   engine.
7. `Controller` base: `render`, `redirect`, `back`, `abort`.
8. `tests/run.php` discovers `tests/Unit/*Test.php` and
   `tests/Feature/*Test.php`; prints summary; exit code 1 on any failure.
   Each test runs in a transaction when the DB is available.
9. Smoke route `/` returns a minimal HTML page until Task 7 replaces it.

**Test:** `tests/Feature/RoutingTest.php` covers GET/POST match, param binding,
404, 405, named URL generation, and middleware order.

**Run:** `php bin/console test`

**Gate:** routing and view rendering work without Laravel.

---

## Task 3 — Database schema, migrate command, category seed

**Files:** `database/schema.sql`, `database/seed/categories.sql`,
`src/Core/Database.php`, `bin/console`.

**Steps**

1. `Database::connectFromConfig(): self`, `pdo()`, `transaction(callable)`,
   `select`, `selectOne`, `execute`, `insertReturningId`. Set
   `PDO::ATTR_ERRMODE = EXCEPTION`, `ATTR_DEFAULT_FETCH_MODE = ASSOC`, and
   `ATTR_EMULATE_PREPARES = false`.
2. Port the schema from `../revenge/database/migrations/` exactly, with
   `IF NOT EXISTS` guards, preserving CHECK constraints and indexes.
3. Tables: `users`, `password_reset_tokens`, `sessions`, `cache`, `jobs`,
   `categories`, `provider_profiles`, `events`, `bookmarks`,
   `moderation_logs`, `event_reports`, `audit_logs`.
4. Add `events.organizer_name`, `events.source_url`,
   `events.source_checked_at`, `events.source_note`.
5. Preserve event constraints: `registration_deadline <= event_start_at`,
   `event_end_at >= event_start_at`, location required for `offline`/`hybrid`,
   valid `status` and `mode`, non-negative `bookmark_count`.
6. Seed exactly three categories: `Lomba`, `Webinar`, `Seminar`, active.
7. `bin/console migrate` connects, executes `schema.sql`, then the seed with
   `ON CONFLICT (slug) DO NOTHING`. It must be safe to run repeatedly.

**Test:** `tests/Feature/SchemaTest.php` verifies tables, constraints (invalid
dates and missing location rejected), indexes, and category count = 3 after two
migrate runs.

**Run:** `createdb lomba_kampus_native_test` once, then
`APP_ENV=test php bin/console migrate && php bin/console test`.

**Gate:** schema is idempotent and constraints reject invalid rows.

---

## Task 4 — Enums, support classes, validator, filter, paginator

**Files:** `src/Enums/*.php`, `src/Support/{DateHelper,TextNormalizer,
SlugGenerator}.php`, `src/Core/{Validator,ValidationException,Paginator}.php`,
`src/DataTransferObjects/CatalogFilter.php`, `src/Http/Requests/FormRequest.php`.

**Steps**

1. Port enum cases exactly from `../revenge/app/Enums/`:
   `UserRole`, `AccountStatus`, `EventStatus`, `EventMode`,
   `VerificationStatus`, `ReportReason`, `ReportStatus`, `ModerationAction`,
   `RuleSeverity`.
2. Port `DateHelper` without Carbon: `dateTime`, `date`, `longDate`, `range`,
   `relative`, `inputValue`, `machineValue`. Convert with
   `DateTimeImmutable` and the configured timezone.
3. Port `TextNormalizer` exactly: conservative and aggressive forms, leet map,
   accents, run-length rules, `contains`, `countPhrase`, `tokens`,
   `containsAllWords`.
4. Port `SlugGenerator`: `base`, `unique`, `isReserved`, max 180 characters,
   word-boundary truncation, numeric suffix.
5. `Validator` supports `required`, `nullable`, `string`, `integer`,
   `boolean`, `email`, `url`, `in`, `max`, `min`, `date`, `after`,
   `after_or_equal`, `before`, `before_or_equal`, `same`, `confirmed`,
   `unique:table,column[,exceptId]`, `exists:table,column`,
   `mimetypes`, `file`, and `max` for files in kilobytes.
6. `FormRequest` base: `authorize()`, `rules()`, `messages()`, `validate()`,
   `input()`, `errors()`, and `after(Validator $v)` hook. `validate()` throws
   `ValidationException` carrying errors and old input.
7. `CatalogFilter` from query params: `q` (max 100), `category`, `mode`,
   `status` in `open|closed`, `sort` in `newest|deadline|popular`, `page`.
   Whitelist every value.
8. `Paginator` with `items`, `total`, `perPage`, `page`, `path`, `query`,
   `lastPage()`, `hasPages()`, `url(int $page)`.

**Test:** unit tests for date ranges, normalizer bypass strings (`gr4t1s`,
`G R A T I S`, `juuudolll`), slug collision, validator cross-field rules, and
pagination URLs.

**Gate:** all support tests green.

---

## Task 5 — Repositories and access rules

**Files:** `src/Repositories/Contracts/*.php`, `src/Repositories/Pdo/*.php`,
`src/Services/Access.php`.

**Steps**

1. Port contract methods from `../revenge/app/Repositories/Contracts/`, with
   native return types: arrays instead of Eloquent, `Paginator` instead of
   `LengthAwarePaginator`.
2. `PdoUserRepository`: create, findById, findActiveByEmail,
   findActiveById, emailExists, updateProfile, updateStatus,
   updatePassword, setRememberToken, paginateForAdmin, countByRole.
3. `PdoCategoryRepository`: allActive, findById, findBySlug, create, update,
   setActive, countAll.
4. `PdoProviderProfileRepository`: create, findById, findByUserId,
   paginateVerificationQueue, findVerifiedById, paginatePublicDirectory,
   findVerifiedBySlug, update, countAll, countByVerificationStatus. Exclude
   `kurasi@internal.invalid` from the public directory and from normal
   management lists.
5. `PdoEventRepository`: `paginatePublicCatalog`, `paginateForProvider`,
   `paginateForAdmin`, `paginateReviewQueue`, `create`, `update`,
   `countSubmissionsSince`, `findPossibleDuplicates`,
   `incrementBookmarkCount`, `decrementBookmarkCount` (never below zero),
   `countAll`, `countByStatus`, `findApprovedBySlug`,
   `findByIdForProvider`, `findById`.
6. Public catalog SQL always filters `status = 'approved'` and
   `registration_deadline >= now()` unless the filter explicitly asks for
   closed events. The default excludes expired deadlines.
7. `PdoBookmarkRepository`: `ON CONFLICT DO NOTHING` on add; delete returns
   affected rows; `exists`; `eventIdsFor`; `paginateForUser` joined to approved
   events; `countForEvent`; `removeForEvent`.
8. `PdoReportRepository`: create, findById, findExisting, open queue, admin
   pagination, updateStatus, countOpenForEvent, countOpen. Unique constraint on
   `(event_id, reporter_user_id)` prevents duplicates.
9. `PdoModerationLogRepository` and `PdoAuditLogRepository`: append-only
   record/read methods. No delete.
10. `Access` methods: `canViewEvent`, `canUpdateEvent`, `canDeleteEvent`,
    `canSubmitEvent`, `canModerateEvent`, `canBookmark`, `canReport`,
    `canViewReport`, `canWithdrawReport`, `canUpdateProviderProfile`,
    `canManageUser`, `canChangeRole`. Ownership checks live here and are called
    from services, not only controllers. Reports require the user role; admin
    reply/status changes require admin.
11. Bind every contract to its PDO implementation in `bootstrap/app.php`.

**Test:** `tests/Feature/RepositoryTest.php` with seeded fixtures inside a
transaction: unique email, slug lookup, approved-only public query, bookmark
idempotency, counter floor at zero, duplicate report rejection, and access
rules for owner vs other provider vs admin.

**Gate:** no SQL in controllers; all repository tests green.

---

## Task 6 — Session, auth, CSRF, middleware, rate limit, mailer

**Files:** `src/Core/{Session,Csrf,Auth,RateLimiter,FileCache,Mailer,
LogMailer}.php`, `src/Http/Middleware/*.php`, `src/Services/AuthService.php`,
`src/Http/Controllers/{AuthController,PasswordResetController}.php`,
`src/Http/Requests/Auth/*.php`, auth views.

**Steps**

1. `Session` uses native file sessions in `storage/sessions`, cookie
   `comphub_session`, `HttpOnly`, `SameSite=Lax`, `Secure` on HTTPS. Regenerate
   on login/logout. Flash data survives one request.
2. `Csrf::token()`, `Csrf::field()`, `Csrf::verify(string $token): bool` using
   `hash_equals`. Rotate on login. `CsrfMiddleware` rejects non-GET without a
   valid token with 419.
3. `Auth`: `attempt`, `login`, `logout`, `user`, `id`, `check`, `role`,
   `refresh`. Password hashes use `password_hash(..., PASSWORD_DEFAULT)`.
   Remember-me: random 32-byte token, store its SHA-256 in
   `users.remember_token`, cookie `user_id:plain-token` signed with
   `hash_hmac` using `APP_KEY`; rotate on login and clear on logout.
4. `RateLimiter` file-backed with atomic `flock`; `hit`, `tooManyAttempts`,
   `availableIn`, `clear`, `remaining`. Keys `route:ip:identity`. Limits:
   login failure 5/15 min per email+IP, register 5/hour, bookmark 30/min,
   report 5/hour, event submit 5/hour, global 120/min.
5. `Mailer` interface with `send(string $to, string $subject, string $body):
   void`; `LogMailer` appends a timestamped block to `storage/logs/mail.log`
   with no secrets. `mail` driver optional.
6. `AuthService`: `registerUser`, `registerProvider`, `login`, `logout`,
   `changePassword`, `providerProfile`. Role is assigned by the service, never
   from input. Suspended accounts cannot log in and get the same message as a
   wrong password.
7. Provider registration accepts an optional verification document. Store
   outside the webroot at `storage/uploads/verification/<random>.<ext>` with a
   random 32-hex name; validate MIME from file contents with `finfo`.
8. Middleware: `auth`, `guest`, `role:user|provider|admin`, `active`,
   `verified`, `csrf`, `throttle:<name>`, `security-headers`. `MiddlewareRegistry`
   resolves names and parses the role argument.
9. Auth views: login, register, provider register, forgot password, reset
   password. Include honeypot `website` fields with `max:0`.
10. Login failure counter is cleared on success. Successful login honors
    `next` only if it is a local path starting with `/` and not `//`.

**Test:** `tests/Feature/AuthTest.php` covers register, duplicate email,
login success/failure, suspension, role never accepted from input, CSRF 419,
rate limit lockout, remember-me, logout, and `next` open-redirect rejection.

**Gate:** auth flows work through the router; no password appears in logs.

---

## Task 7 — Public shell, catalog, detail, and hard guest gate

**Files:** `public/assets/css/app.css`, `public/assets/js/app.js`,
`resources/views/partials/*`, `resources/views/components/*`,
`resources/views/pages/home.php`, `resources/views/events/index.php`,
`resources/views/events/show.php`, `src/Http/Controllers/{HomeController,
EventCatalogController}.php`, `routes/web.php`.

**Steps**

1. Build the 60/30/10 token set: canvas `#F2F4F7`, ink `#111827`, accent
   `#2563EB`; status colors `#15803D`, `#B45309`, `#B91C1C`, `#0369A1`.
   Glass recipe only on navbar, filter panel, event cards, sheets, and modals:
   `rgba(255,255,255,.62)`, `backdrop-filter: blur(16px)`, 1px
   `rgba(17,24,39,.10)` border. No purple, gradient text, blobs, or emoji.
   System font stack. Reduced-motion media query disables transforms.
2. Base layout: skip link, semantic header/nav/main/footer, visible focus
   rings, 44px minimum targets, one `h1` per page.
3. Home: search, three category shortcuts, latest open events, deadline-soon
   section, provider directory link. No fake statistics or testimonials.
4. Catalog: query filters, result count, sort, pagination, empty state. Search
   covers title, organizer, campus, and description.
5. Event card: title, organizer, category, mode, date range, deadline status,
   bookmark state. Entire card links to detail; bookmark is a separate button.
6. Detail: all public fields, source note, source domain text, checked date,
   and a status band (`Buka`, `Segera tutup`, `Ditutup`).
7. Guest gate in `EventCatalogController::show`: compute
   `$isAuthenticated`. For guests, unset `registration_url` and `source_url`
   from the event array before rendering and before building OG/JSON data.
   Pass `source_domain` only. CTA points to `/masuk?next=/kegiatan/{slug}`.
   Never emit the URL in a hidden input, JSON-LD, meta tag, or data attribute.
8. Authenticated users see `Lanjut ke pendaftaran` with
   `target="_blank" rel="noopener noreferrer"`. Bookmark button posts to the
   bookmark route.
9. `app.js` handles filter panel toggle, mobile nav, and dismissible flash.
   No framework.
10. `records.view` is not needed; views are not tracked.

**Test:** `tests/Feature/GuestGateTest.php` asserts a guest detail response
does not contain the registration or source URL, does contain the source
domain, and has a login CTA with an encoded local `next`. Auth response
contains both URLs with `rel="noopener noreferrer"`. Catalog tests assert only
approved and open events appear, filters and pagination work, and expired
events are absent by default.

**Gate:** guest HTML contains zero external registration URLs.

---

## Task 8 — Static pages and provider directory

**Files:** `src/Http/Controllers/{StaticPageController,
ProviderDirectoryController}.php`, `resources/views/pages/*`,
`resources/views/providers/*`.

**Steps**

1. Static pages: about, moderation policy (seven rules and the no-auto-publish
   rule), provider guide, privacy, contact. Content must state real behavior:
   what data is stored, why, retention, and how to request removal. No invented
   address or phone number.
2. Provider directory lists only verified profiles, excluding the curation
   account. Search by organization, campus, or keyword. Pagination 12.
3. Provider public page shows organization, campus, description, website
   domain as a link only for authenticated users? The website is an
   organization profile, not event registration; it may be public if it is the
   official site. Keep it public only when it is the profile's official
   website and has https. No private email exposure.
4. Profile page lists approved events from that provider, respecting the guest
   gate.

**Test:** directory excludes unverified and curation profiles; search works;
verified page 200; slug 404 for unknown.

**Gate:** static and directory pages render without auth.

---

## Task 9 — Bookmarks, user dashboard, profile, password, report create

**Files:** `src/Services/{BookmarkService,ReportService}.php`,
`src/Http/Controllers/{BookmarkController,UserDashboardController,
ProfileController,ReportController}.php`, requests, user views, routes.

**Steps**

1. `BookmarkService`: add/remove/toggle/isSaved/paginateForUser/savedMap,
   updating `events.bookmark_count` in the same transaction as the bookmark
   row. Add is idempotent; remove floors the counter at zero.
2. Dashboard: saved count, open reports count, account status, profile link.
   No fake activity.
3. Profile edit: name and email unique except self. Password change verifies
   the current password, hashes the new one, and invalidates other sessions
   (delete session files whose payload contains the user id; file sessions are
   per-ID, so store `auth_user_id` in the session and scan the directory
   safely).
4. Report create: reason enum, description required for `other`, duplicate
   report reuses the existing open report instead of creating a second.
   Report requires `role:user` and an approved event.
5. Withdraw own pending report only.
6. Routes: `POST/DELETE /kegiatan/{event:id}/bookmark`; `DELETE
   /laporan/{report}` owner-only.

**Test:** bookmark idempotency and counter, same-user duplicate report, withdraw
permission, password change invalidates other sessions, profile uniqueness.

**Gate:** user flows complete without admin involvement.

---

## Task 10 — Provider registration, profile, document upload, dashboard

**Files:** `src/Http/Controllers/{ProviderDashboardController,
ProviderProfileController}.php`, requests, provider views,
`src/Services/ProviderVerificationService.php`.

**Steps**

1. Registration creates user role `provider` plus profile with
   `verification_status = pending`. Slug from organization name, unique.
2. Dashboard shows verification status, note, event counts by status, and
   document status. No events can be created until verified.
3. Profile edit updates organization fields and optionally replaces the
   document. Resubmit sets status to pending and clears rejection note.
4. Document download is not exposed on the provider side; only admin can
   download via Task 14. The uploaded filename is random and never shown.
5. Provider cannot change role, verification status, or verified_by.

**Test:** provider registration, pending cannot create event, document MIME
rejection for PHP/HTML, 2 MB limit, resubmit transition, slug collision.

**Gate:** provider onboarding works end to end.

---

## Task 11 — Provider event CRUD and submission

**Files:** `src/Services/EventService.php`,
`src/Http/Controllers/ProviderEventController.php`,
`src/Http/Requests/Event/*.php`, provider event views.

**Steps**

1. `EventService::createDraft(providerId, data)`: owner is the provider
   profile; status `draft`; slug unique; category must be active.
2. `update`: only draft or changes_requested events are editable. Approved
   events must be withdrawn first. Ownership mismatch returns 404, not 403.
3. `submit`: validate moderation, write event updates and moderation log in one
   transaction. Status becomes `pending` or `rejected` based on verdict; never
   `approved`.
4. `resubmit`: only from changes_requested or rejected; clears prior summary
   and re-runs moderation.
5. `withdraw`: approved -> draft or changes_requested? Preserve old behavior:
   approved -> changes_requested only through admin; provider withdraws a
   pending submission back to draft. Read the old service and port its exact
   transitions.
6. `delete`: only draft. Soft behavior not needed; delete cascades logs and
   bookmarks. Approved events are archived by admin, not deleted.
7. Views: list with status filters, create/edit form with datetime-local
   inputs, validation errors, moderation summary on rejected/changes_requested.
8. Form rules match old: title 15–180, description 120–8000, requirements
   ≤3000, registration URL http/https, mode enum, location required for
   offline/hybrid, platform URL required for online, deadline after now and
   ≤ start, end ≥ start, honeypot empty.

**Test:** draft create, ownership 404, edit restrictions, submit runs
moderation, approved cannot be edited, deadline/location validation.

**Gate:** provider event lifecycle matches old behavior.

---

## Task 12 — Moderation engine (seven rules)

**Files:** `src/Services/Moderation/*`, `src/Services/Moderation/Rules/*`,
`config/moderation.php`.

**Steps**

1. Port `config/moderation.php` exactly: thresholds reject 60, flag 25, cap
   100; weights keyword 100, duplicate 30, rate limit 40, suspicious URL 25,
   contact leak 20, date sanity 30, content quality 10.
2. `ModerationContext`: event fields, provider profile, provider trust flag,
   candidate duplicates, submission counts. `allFreeText`, `allUrls`.
3. `RuleResult`: rule key, severity, weight, message, metadata.
   `ModerationVerdict`: results, total score capped at 100, decision
   (`reject` if score >= 60, else `flag` if >= 25, else `pass`),
   `resultingStatus()` (`rejected` or `pending`), `summary`, `topReasons`.
4. Rules:
   - `BlockedKeywordRule`: normalized aggressive match; blocked list; pattern
     words all present; weight 100.
   - `ContactLeakRule`: WhatsApp, Telegram, generic phone, email patterns;
     skip email check on registration URL per config.
   - `ContentQualityRule`: title word/char minimum, description 120 chars;
     skipped for trusted verified providers with 3 clean submissions in 90
     days.
   - `DateSanityRule`: lead time ≥2 hours, max duration 30 days, no past
     events.
   - `DuplicateEventRule`: same provider, title similarity ≥0.85, start date
     within 3 days; use normalized Levenshtein or similar; weight 30.
   - `RateLimitRule`: 5/hour, 20/day, 30-minute cooldown; weight 40.
   - `SuspiciousUrlRule`: shorteners, paste sites, executable extensions,
     non-HTTPS, >3 links; weight 25.
5. `ModerationEngine::evaluate(context): ModerationVerdict` runs all seven,
   sums weights, caps at 100. It never approves.
6. Log every rule result in `moderation_logs.metadata` as a JSON array,
   filtered of secrets. Never log the full description.

**Test:** port the old moderation tests and add table-driven cases for each
rule and combined scores: keyword alone rejects; flag threshold; cap; trusted
provider still rejects on blocked keyword; date boundary at 2 hours; duplicate
similarity; URL schemes; contact patterns; rate limit.

**Gate:** all moderation tests green; no auto-approve path exists.

---

## Task 13 — Admin dashboard, moderation queue, decisions, audit

**Files:** `src/Http/Controllers/Admin/{AdminDashboardController,
EventModerationController}.php`, requests, admin views,
`src/Services/AuditLogService.php`.

**Steps**

1. Dashboard counts: pending review, changes requested, open reports, pending
   providers, approved events, total users. Links to queues.
2. Review queue orders by risk score descending, then oldest submission.
   Filters by status and provider.
3. Event detail shows event data, provider identity, full moderation verdict,
   rule history, and prior admin decisions.
4. `POST /admin/kegiatan/{event:id}/moderasi` with action
   `approve|reject|request_changes|archive|unarchive` and a note/reason.
   Validate transitions against `EventStatus::allowedTransitions()`. Reject and
   request-changes require a reason. Approve sets `published_at` once. Archive
   clears public visibility.
5. Every transition writes `moderation_logs` and `audit_logs` in the same
   transaction.
6. Admin cannot approve an event with an expired deadline. The UI says why.

**Test:** approve pending, reject with reason required, illegal transition
rejected, archive removes from public catalog, audit rows written, risk order.

**Gate:** admin decisions are atomic and audited.

---

## Task 14 — Provider verification and private document download

**Files:** `src/Http/Controllers/Admin/{ProviderVerificationController,
VerificationDocumentController}.php`, requests, views.

**Steps**

1. Verification queue oldest first, with organization, campus, official
   email, website domain, document presence, and prior note.
2. Decisions: verify, reject with reason, reopen. Verify sets
   `verified_at`, `verified_by`, clears rejection note. Reject requires a
   reason.
3. `GET /admin/penyelenggara/{provider:id}/dokumen` streams the private file
   only to admins. Use `realpath` containment under
   `storage/uploads/verification/`, check MIME, and send
   `Content-Disposition: attachment` plus `X-Content-Type-Options: nosniff`.
   Missing file -> 404, never a directory listing.
4. Audit provider verification, rejection, and reopen.
5. Rejected providers can resubmit and return to pending.

**Test:** admin-only download, path traversal blocked, missing file 404,
verify/reject transitions, audit rows.

**Gate:** private documents are not web-accessible.

---

## Task 15 — Admin reports, users, categories, audit log

**Files:** `src/Http/Controllers/Admin/{ReportReviewController,
UserManagementController,CategoryController,AuditLogController}.php`,
requests, views.

**Steps**

1. Report queue: open and reviewing, urgent reasons first, then oldest.
   Actions: open (reviewing), resolve with note, dismiss with note. Only admin.
   Reporter sees status; admin note is not exposed to the reporter unless the
   design says otherwise (it does not).
2. User management: list/search/filter by role and status. Suspend with note,
   reactivate. Role change only between user and provider; never promote to
   admin through the UI. Admin cannot suspend or demote self. The curation
   account is hidden and immutable.
3. Categories: list with active counts, create, rename, deactivate. Deactivate
   does not delete events. Category delete is allowed only when no events
   reference it; otherwise return a validation error.
4. Audit log: read-only filters by actor, action, target type, date. Pagination.
5. All admin mutations write audit entries in the same transaction.

**Test:** report transitions, admin note privacy, self-suspension blocked,
curation account hidden, category delete blocked when in use, audit filter.

**Gate:** admin surface complete.

---

## Task 16 — Password reset and auth hardening

**Files:** `src/Http/Controllers/PasswordResetController.php`,
`src/Services/AuthService.php`, requests, auth views, `config/security.php`.

**Steps**

1. Request reset: normalize email, always show the same message whether or not
   the account exists. Rate limit by IP+email. Generate 32 random bytes,
   store only `hash('sha256', $token)` with expiry, delete older tokens for the
   same email.
2. Log mail: link `${APP_URL}/atur-sandi/{token}?email={urlencoded}` with
   subject and expiry. Never log the token to the application log; only the
   mail log.
3. Reset form: validate token hash, expiry, email match, password policy
   (8+, letters and numbers, confirmed). Update password in a transaction,
   delete token, invalidate sessions, log the user in? Old behavior: redirect
   to login with success. Keep old behavior.
4. Login hardening: constant-time password check, failure counter, clear on
   success, generic error message. Regenerate session ID.
5. Add `Cache-Control: no-store` on auth and admin responses.

**Test:** unknown email does not reveal existence, token one-time use, expired
token rejected, password policy, session invalidation after reset.

**Gate:** reset flow works with the log mailer.

---

## Task 17 — Expiry CLI, importer, real data, admin bootstrap, README

**Files:** `src/Services/{EventExpiryService,EventImporter}.php`,
`bin/console`, `data/events/2026-10-28.json`, `README.md`, `.env.example`.

**Steps**

**17a. Expiry.** `markExpired()` updates approved/pending events whose
`registration_deadline < now()` to `expired`, writes moderation and audit rows,
and returns the count. `purgeBookmarksOfExpired()` optionally removes stale
bookmarks. `bin/console expire-events` runs both and prints counts.

**17b. Importer.** `EventImporter::import(string $file, bool $dryRun = false):
array`:

- Ensure the system account `kurasi@internal.invalid` exists with role
  provider, random password hash that is never printed, and profile
  `Tim Kurasi CompHub`, campus `Lintas kampus`, slug `tim-kurasi-comphub`,
  verification `verified`. The account cannot log in (`password_hash` of 64
  random bytes; no reset path) and is hidden from admin user lists.
- Validate JSON shape, required fields, enum values, dates, and URL schemes.
- Reject entries missing `source_url`, `source_checked_at`, `organizer`, or
  `registration_url`.
- Slug identity: insert new; update only rows owned by the curation profile;
  reject a slug owned by another provider and report it.
- Allow repeated `registration_url`/`source_url` with a warning.
- Insert with status from JSON only if `approved` or `draft`; any other status
  is rejected. Importing `approved` is an operator action and writes an
  `event.imported` audit row.
- Wrap in one transaction; `--dry-run` rolls back and prints the same report.
- Output counts: inserted, updated, skipped, warnings, errors.

**17c. Real data verification (mandatory; no invention).** Today is
2026-10-28. For each candidate, open the official page with `webfetch`, confirm
the deadline is after today, confirm the registration URL responds, and record
the check time with `TZ=Asia/Jakarta date -Iseconds`. Candidate shortlist from
the design:

- PERTAPSI Tax Competition 2026
- IT-CAS 2026
- GenBI Essay Innovation 2026
- Business Plan Competition Fisipreneur 2026
- LKTIN EcoGeneration 2026
- Festival Bela Negara Menwa UNM
- LOGICODIX Hackathon 2026
- Kompetisi Esai Nasional HMTI Udayana
- Seno Hackathon ITS 2026
- EDUPRO 2026
- ICDESS 2026
- ICETISI 2027
- KelasRiset 2 November 2026
- IFERP November 2026
- Webinar Beasiswa 30 November 2026
- InGuide November 2026

Rules: include only entries with a clear organizer, readable dates, mode,
location/platform, an open registration or official participation URL, and a
future deadline. If a deadline is uncertain, set `source_note` to
`tanggal tutup belum dipastikan` and do not claim `Buka`; the UI must show the
uncertainty. Exclude closed, student-irrelevant, or Instagram-only entries.
Write original descriptions of at least 120 characters; do not copy source
text. Minimum viable dataset: the three PDF-verified entries that remain valid
(ICETISI 2027, Seno Hackathon ITS 2026, InGuide November 2026) plus any other
verified-open candidates. If a minimum entry cannot be verified, the importer
must not fabricate it; report the gap instead.

**17d. Admin bootstrap.** `bin/console create-admin` reads `ADMIN_NAME`,
`ADMIN_EMAIL`, `ADMIN_PASSWORD`. Refuse weak passwords (<12 chars or missing
letters/numbers), refuse existing email, never echo the password, and print
only the created email. If env vars are absent, read interactive hidden input;
never write a default password.

**17e. README.** Setup without Composer: PHP extensions, `createdb`, `.env`,
`php bin/console migrate`, `php bin/console create-admin`,
`php bin/console import-events data/events/2026-10-28.json`,
`php -S 127.0.0.1:8080 -t public`, `php bin/console test`. Document file
sessions/cache, log mailer, single-server assumption, backup/restore, and the
curation account policy. No demo credentials.

**Test:** importer idempotency with a test fixture, slug ownership conflict,
missing provenance rejection, dry-run rollback, expiry idempotency, admin
password policy. Real data is validated by running the importer against the
test database first, then checking each public detail page.

**Gate:** imported real events render; every curated row has provenance; a
second import changes nothing except `updated_at`/`source_checked_at`.

---

## Task 18 — Final UI pass, security review, acceptance

**Files:** all views and `public/assets/*`, `README.md`, tests as needed.

**Steps**

1. Visual pass on every page: consistent spacing scale, type scale, focus
   states, empty/loading/error states, mobile and desktop. Glass only where the
   design allows. No unexplained decoration.
2. Accessibility: keyboard traversal, skip link, labels, error association,
   contrast, target size, `aria-live` for flash, reduced motion.
3. Security review:
   - Search for `registration_url` and `source_url` in guest views and JSON.
   - Verify CSRF on every POST/PUT/PATCH/DELETE.
   - Verify SQL uses placeholders only.
   - Verify upload containment and MIME checks.
   - Verify admin routes require `role:admin`.
   - Verify `next` redirects are local-only.
   - Verify no secrets in `storage/logs`, `.env.example`, or git status.
4. HTTP smoke with the built-in server against the test database: home,
   catalog filters, guest detail gate, login, register, bookmark, provider
   dashboard, admin queue, report queue, 404, 419.
5. Run `php bin/console test`; run PHP lint over all PHP files; run
   `git status --short` and confirm no unintended files.
6. Update README with the exact verified commands and any operational limits.

**Acceptance matrix**

| Requirement | Proof |
|---|---|
| No Laravel/Composer dependency | `grep -R "Illuminate\|Laravel\|vendor/autoload" src public bootstrap routes` returns nothing |
| Guest sees all event info but no external URLs | `GuestGateTest` + HTML grep |
| Auth sees registration link safely | feature test asserts `rel="noopener noreferrer"` |
| Seven moderation rules, no auto-publish | moderation tests + code search |
| Real data only, provenance present | SQL check: curated rows have source_url and source_checked_at |
| No demo data | test DB seeded only categories; production import file has no fixtures |
| Admin bootstrap from env | `create-admin` test |
| Password reset works | reset tests + mail log |
| UI tokens 60/30/10 + glass | `app.css` token block + visual QA |
| WCAG 2.2 AA basics | keyboard/contrast/accessibility checklist |
| Schema constraints | `SchemaTest` |
| Routes parity | `RoutingTest` + route inventory comparison with old `routes/web.php` |

**Gate:** all tests green, smoke pages 200/expected, guest gate proven, no
secret or demo data, README accurate.

---

## 3. Parity checklist against the old app

- Public home, catalog, filters, search, sort, pagination.
- Event detail by slug; private routes by numeric ID.
- Provider directory and public provider page.
- Static pages: about, moderation, provider guide, privacy, contact.
- Register user, register provider, login, logout, forgot/reset password.
- User dashboard, bookmarks, profile, password change, report create/withdraw.
- Provider dashboard, profile, document upload/resubmit, event CRUD, submit,
  withdraw.
- Admin dashboard, event moderation, provider verification, document download,
  reports, users, categories, audit log.
- Moderation rules and risk ranking.
- Expiry command.
- Seed categories only; no demo accounts/events/reports.
- New: curated provenance fields, curation badge, hard guest gate, native
  architecture, redesigned UI.

**Routes to preserve** (names from `../revenge/routes/web.php`):
`home`, `events.index`, `events.show`, `providers.index`, `providers.show`,
`pages.*`, `events.bookmark.store`, `events.bookmark.destroy`, `auth.*`,
`user.*`, `provider.*`, `admin.*`.

**Known intentional differences:** no `records.view`, no queue worker, no
Laravel scheduler, no Blade components, no Eloquent scopes, no `artisan`.
Document them in README.

---

## 4. Risks and mitigations

| Risk | Mitigation |
|---|---|
| Hand-rolled auth/CSRF bugs | Feature tests for each flow; constant-time checks; session rotation |
| Guest URL leak through OG/JSON | Central `eventForView()` gate; test greps full response body |
| Real data goes stale | `source_checked_at` + `expire-events`; source note for uncertain deadlines |
| Importer overwrites provider data | Slug ownership check; only curation rows update |
| File sessions/cache on multi-server | Document single-server assumption; directory permissions 0700 |
| Log mailer loses reset emails | Document; optional `mail` driver config |
| Moderation false positives | No auto-approve; admin queue; score is priority, not verdict |
| Upload path traversal | Random names, realpath containment, MIME from contents, no direct web path |
| Scope creep | Stop at the parity checklist; no speculative features |

---

## 5. Execution order and stop conditions

Run tasks 1→18 in order. A task is done only when its test command and the
full suite pass. If a task exposes a design flaw, stop and amend the design
before coding around it. If real data cannot be verified, stop at Task 17c and
report the gap; do not invent events. Do not commit unless the user explicitly
asks.

---

## Amendemen 2026-10-29 — Urutan eksekusi baru

Amendemen desain 2026-10-29 mengubah lingkup. Status per 2026-10-29:

- Selesai: Task 1–8 (skeleton, router, schema, dukungan, repository, auth,
  shell publik, katalog, detail, direktori penyelenggara, halaman statis).
- Selesai sebagian: Task 9 hanya bookmark; dasbor pengguna, profil, dan
  laporan belum.
- Belum: Task 10–18.

Kerjakan ulang dengan urutan ini. Jangan sentuh `../revenge`.

### M1 — Gerbang detail penuh

- `EventCatalogController::show` mengembalikan `302 /masuk?next=...` untuk
  tamu. Jangan render detail untuk tamu.
- Kartu beranda dan katalog menampilkan ringkasan. Tombol detail dan simpan
  tamu menuju login.
- Perbarui `GuestGateTest`: tamu dapat `302`; respons login memuat `next`
  lokal; pengguna masuk dapat detail dan `registration_url`;
  `rel="noopener noreferrer"` ada.
- Hapus penyembunyian URL pasca-render yang tidak lagi dipakai.

### M2 — Skema tambahan

- `users.events_seen_at TIMESTAMPTZ NULL`.
- `events.deadline_confirmed BOOLEAN NOT NULL DEFAULT TRUE`.
- Perbarui `database/schema.sql` idempoten. Perbarui `SchemaTest`.
- Pengguna baru: `events_seen_at = now()` saat registrasi.

### M3 — Dasbor pengguna dan simpanan

- `GET /tersimpan`: daftar bookmark pengguna, paginasi 12, hanya event
  `approved`.
- `GET /dasbor`: jumlah simpanan, jumlah notifikasi baru, tautan profil.
- `POST /keluar` tetap.
- Profil minimal: ubah nama dan kata sandi. Laporan tetap ditunda.
- Tambah `UserDashboardController`, `ProfileController`, service yang sudah
  ada, view, request, dan tes.

### M4 — Notifikasi dalam aplikasi

- `NotificationController`: `index`, `markRead`.
- Repository: `countNewSince`, `paginateNewSince`, `markSeen`.
- Lonceng di navbar untuk pengguna masuk. Badge memakai jumlah baru.
- `POST /notifikasi/tandai-dibaca` dengan CSRF.
- Tes: pengguna baru badge 0; event baru menambah badge; tandai dibaca
  menghapus badge; tamu tidak melihat lonceng; event belum `approved` tidak
  dihitung.

### M5 — Importer dan data nyata

- Port `EventImporter` dari rencana Task 17b, termasuk akun kurasi
  `kurasi@internal.invalid`.
- `bin/console import-events <file>` dan `--dry-run`.
- `data/events/2026-10-29.json` berisi entri terverifikasi. Wajib:
  ICETISI 2027, Hackathon ITS 2026, INGUIDE Course 2. Tambahan hanya dari
  URL resmi/agregator yang bisa dibuka saat impor.
- Entri tanpa provenance ditolak. Entri dengan tenggat tidak pasti memakai
  `deadline_confirmed = false`.
- Tes: idempoten, dry-run rollback, provenance wajib, slug milik penyedia
  lain ditolak.

### M6 — UI pass dan bukti

- Beranda: hero ringkas, filter kategori, kartu informasi lomba.
- Katalog: ringkasan, filter, urutan, paginasi.
- Detail: hanya pengguna masuk.
- Notifikasi dan tersimpan: state kosong, error, dan sukses.
- 60/30/10 dan glass terbatas; fokus, kontras, target 44 px, reduced motion.
- Jalankan `php bin/console test`, `php -l` semua file PHP, lalu smoke HTTP
  dengan `php -S`.

### Ditunda

Task 10–16 dan bagian parity Task 18. Rute penyelenggara disembunyikan;
pendaftaran penyelenggara belum dibuka. Jangan hapus file pendukung yang sudah
ada tanpa alasan.
