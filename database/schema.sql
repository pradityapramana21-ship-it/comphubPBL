-- CompHub Native schema. Safe to run repeatedly.
-- All timestamps are timestamptz. Display conversion happens in PHP.

CREATE TABLE IF NOT EXISTS users (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    email_verified_at TIMESTAMPTZ NULL,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL DEFAULT 'user',
    account_status VARCHAR(20) NOT NULL DEFAULT 'active',
    suspended_at TIMESTAMPTZ NULL,
    suspension_note TEXT NULL,
    remember_token VARCHAR(100) NULL,
    events_seen_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT users_role_check CHECK (role IN ('user', 'provider', 'admin')),
    CONSTRAINT users_account_status_check CHECK (account_status IN ('active', 'suspended')),
    CONSTRAINT users_suspension_check CHECK (account_status <> 'suspended' OR suspended_at IS NOT NULL)
);

CREATE INDEX IF NOT EXISTS users_role_status_index ON users (role, account_status);

CREATE TABLE IF NOT EXISTS password_reset_tokens (
    email VARCHAR(255) PRIMARY KEY,
    token VARCHAR(255) NOT NULL,
    created_at TIMESTAMPTZ NULL
);

CREATE TABLE IF NOT EXISTS sessions (
    id VARCHAR(255) PRIMARY KEY,
    user_id BIGINT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    payload TEXT NOT NULL,
    last_activity INTEGER NOT NULL
);

CREATE INDEX IF NOT EXISTS sessions_user_id_index ON sessions (user_id);
CREATE INDEX IF NOT EXISTS sessions_last_activity_index ON sessions (last_activity);

CREATE TABLE IF NOT EXISTS cache (
    key VARCHAR(255) PRIMARY KEY,
    value TEXT NOT NULL,
    expiration BIGINT NOT NULL
);

CREATE INDEX IF NOT EXISTS cache_expiration_index ON cache (expiration);

CREATE TABLE IF NOT EXISTS cache_locks (
    key VARCHAR(255) PRIMARY KEY,
    owner VARCHAR(255) NOT NULL,
    expiration BIGINT NOT NULL
);

CREATE INDEX IF NOT EXISTS cache_locks_expiration_index ON cache_locks (expiration);

CREATE TABLE IF NOT EXISTS jobs (
    id BIGSERIAL PRIMARY KEY,
    queue VARCHAR(255) NOT NULL,
    payload TEXT NOT NULL,
    attempts SMALLINT NOT NULL,
    reserved_at INTEGER NULL,
    available_at INTEGER NOT NULL,
    created_at INTEGER NOT NULL
);

CREATE INDEX IF NOT EXISTS jobs_queue_index ON jobs (queue);

CREATE TABLE IF NOT EXISTS job_batches (
    id VARCHAR(255) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    total_jobs INTEGER NOT NULL,
    pending_jobs INTEGER NOT NULL,
    failed_jobs INTEGER NOT NULL,
    failed_job_ids TEXT NOT NULL,
    options TEXT NULL,
    cancelled_at INTEGER NULL,
    created_at INTEGER NOT NULL,
    finished_at INTEGER NULL
);

CREATE TABLE IF NOT EXISTS failed_jobs (
    id BIGSERIAL PRIMARY KEY,
    uuid VARCHAR(255) NOT NULL UNIQUE,
    connection VARCHAR(255) NOT NULL,
    queue VARCHAR(255) NOT NULL,
    payload TEXT NOT NULL,
    exception TEXT NOT NULL,
    failed_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS failed_jobs_connection_queue_failed_at_index
    ON failed_jobs (connection, queue, failed_at);

CREATE TABLE IF NOT EXISTS categories (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(80) NOT NULL UNIQUE,
    slug VARCHAR(80) NOT NULL UNIQUE,
    description TEXT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS provider_profiles (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
    organization_name VARCHAR(150) NOT NULL,
    campus_name VARCHAR(150) NOT NULL,
    official_email VARCHAR(190) NOT NULL,
    website_url VARCHAR(255) NULL,
    description TEXT NULL,
    verification_status VARCHAR(20) NOT NULL DEFAULT 'pending',
    verification_document_path VARCHAR(255) NULL,
    verification_note TEXT NULL,
    verified_at TIMESTAMPTZ NULL,
    verified_by BIGINT NULL REFERENCES users(id) ON DELETE SET NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT provider_profiles_verification_check
        CHECK (verification_status IN ('pending', 'verified', 'rejected')),
    CONSTRAINT provider_profiles_verified_at_check
        CHECK ((verification_status = 'verified') = (verified_at IS NOT NULL))
);

CREATE INDEX IF NOT EXISTS provider_profiles_verification_index
    ON provider_profiles (verification_status, created_at);
CREATE INDEX IF NOT EXISTS provider_profiles_organization_index
    ON provider_profiles (organization_name);

CREATE TABLE IF NOT EXISTS events (
    id BIGSERIAL PRIMARY KEY,
    provider_profile_id BIGINT NOT NULL REFERENCES provider_profiles(id) ON DELETE CASCADE,
    category_id BIGINT NOT NULL REFERENCES categories(id) ON DELETE RESTRICT,
    title VARCHAR(180) NOT NULL,
    slug VARCHAR(200) NOT NULL UNIQUE,
    description TEXT NOT NULL,
    requirements TEXT NULL,
    registration_url VARCHAR(500) NOT NULL,
    mode VARCHAR(20) NOT NULL,
    location_text VARCHAR(255) NULL,
    platform_url VARCHAR(500) NULL,
    event_start_at TIMESTAMPTZ NOT NULL,
    event_end_at TIMESTAMPTZ NOT NULL,
    registration_deadline TIMESTAMPTZ NOT NULL,
    deadline_confirmed BOOLEAN NOT NULL DEFAULT TRUE,
    status VARCHAR(30) NOT NULL DEFAULT 'draft',
    risk_score SMALLINT NOT NULL DEFAULT 0,
    submitted_at TIMESTAMPTZ NULL,
    published_at TIMESTAMPTZ NULL,
    archived_at TIMESTAMPTZ NULL,
    rejection_reason TEXT NULL,
    view_count INTEGER NOT NULL DEFAULT 0,
    bookmark_count INTEGER NOT NULL DEFAULT 0,
    organizer_name VARCHAR(180) NULL,
    event_type VARCHAR(20) NULL,
    registration_start_at TIMESTAMPTZ NULL,
    poster_path VARCHAR(255) NULL,
    guidebook_path VARCHAR(255) NULL,
    organizer_email VARCHAR(190) NULL,
    contact_number VARCHAR(40) NULL,
    revision_token_hash CHAR(64) NULL,
    revision_token_expires_at TIMESTAMPTZ NULL,
    revision_token_revoked_at TIMESTAMPTZ NULL,
    revision_token_last_used_at TIMESTAMPTZ NULL,
    source_url VARCHAR(500) NULL,
    source_checked_at TIMESTAMPTZ NULL,
    source_note TEXT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT events_status_check
        CHECK (status IN ('draft','pending','changes_requested','approved','rejected','expired','archived')),
    CONSTRAINT events_mode_check CHECK (mode IN ('online','offline','hybrid')),
    CONSTRAINT events_risk_score_check CHECK (risk_score >= 0 AND risk_score <= 100),
    CONSTRAINT events_time_order_check CHECK (event_end_at >= event_start_at),
    CONSTRAINT events_deadline_check CHECK (registration_deadline <= event_start_at),
    CONSTRAINT events_location_check CHECK (mode = 'online' OR location_text IS NOT NULL),
    CONSTRAINT events_published_check CHECK (published_at IS NOT NULL OR status <> 'approved'),
    CONSTRAINT events_bookmark_count_check CHECK (bookmark_count >= 0)
);

CREATE INDEX IF NOT EXISTS events_status_deadline_index ON events (status, registration_deadline);
CREATE INDEX IF NOT EXISTS events_category_start_index ON events (category_id, event_start_at);
CREATE INDEX IF NOT EXISTS events_provider_created_index ON events (provider_profile_id, created_at);
CREATE INDEX IF NOT EXISTS events_status_risk_index ON events (status, risk_score);
CREATE INDEX IF NOT EXISTS events_bookmark_count_index ON events (bookmark_count DESC);
CREATE INDEX IF NOT EXISTS events_status_published_index ON events (status, published_at DESC);

ALTER TABLE users ADD COLUMN IF NOT EXISTS events_seen_at TIMESTAMPTZ NOT NULL DEFAULT now();
ALTER TABLE events ADD COLUMN IF NOT EXISTS deadline_confirmed BOOLEAN NOT NULL DEFAULT TRUE;
ALTER TABLE events ADD COLUMN IF NOT EXISTS event_type VARCHAR(20) NULL;
ALTER TABLE events ADD COLUMN IF NOT EXISTS registration_start_at TIMESTAMPTZ NULL;
ALTER TABLE events ADD COLUMN IF NOT EXISTS poster_path VARCHAR(255) NULL;
ALTER TABLE events ADD COLUMN IF NOT EXISTS guidebook_path VARCHAR(255) NULL;
ALTER TABLE events ADD COLUMN IF NOT EXISTS organizer_email VARCHAR(190) NULL;
ALTER TABLE events ADD COLUMN IF NOT EXISTS contact_number VARCHAR(40) NULL;
ALTER TABLE events ADD COLUMN IF NOT EXISTS revision_token_hash CHAR(64) NULL;
ALTER TABLE events ADD COLUMN IF NOT EXISTS revision_token_expires_at TIMESTAMPTZ NULL;
ALTER TABLE events ADD COLUMN IF NOT EXISTS revision_token_revoked_at TIMESTAMPTZ NULL;
ALTER TABLE events ADD COLUMN IF NOT EXISTS revision_token_last_used_at TIMESTAMPTZ NULL;

CREATE INDEX IF NOT EXISTS events_event_type_start_index ON events (event_type, event_start_at);
CREATE INDEX IF NOT EXISTS events_registration_start_index ON events (registration_start_at, registration_deadline);
CREATE INDEX IF NOT EXISTS events_archived_index ON events (archived_at, status);
CREATE UNIQUE INDEX IF NOT EXISTS events_revision_token_hash_index
    ON events (revision_token_hash) WHERE revision_token_hash IS NOT NULL;

CREATE TABLE IF NOT EXISTS bookmarks (
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    event_id BIGINT NOT NULL REFERENCES events(id) ON DELETE CASCADE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    PRIMARY KEY (user_id, event_id)
);

CREATE INDEX IF NOT EXISTS bookmarks_event_index ON bookmarks (event_id);

CREATE TABLE IF NOT EXISTS moderation_logs (
    id BIGSERIAL PRIMARY KEY,
    event_id BIGINT NOT NULL REFERENCES events(id) ON DELETE CASCADE,
    actor_user_id BIGINT NULL REFERENCES users(id) ON DELETE SET NULL,
    action VARCHAR(30) NOT NULL,
    rule_key VARCHAR(50) NULL,
    reason TEXT NULL,
    risk_score SMALLINT NULL,
    metadata JSONB NULL,
    previous_status VARCHAR(30) NULL,
    new_status VARCHAR(30) NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS moderation_logs_event_created_index
    ON moderation_logs (event_id, created_at);
CREATE INDEX IF NOT EXISTS moderation_logs_rule_key_index ON moderation_logs (rule_key);
CREATE INDEX IF NOT EXISTS moderation_logs_action_created_index
    ON moderation_logs (action, created_at);

ALTER TABLE moderation_logs ADD COLUMN IF NOT EXISTS previous_status VARCHAR(30) NULL;
ALTER TABLE moderation_logs ADD COLUMN IF NOT EXISTS new_status VARCHAR(30) NULL;

CREATE TABLE IF NOT EXISTS event_reports (
    id BIGSERIAL PRIMARY KEY,
    event_id BIGINT NOT NULL REFERENCES events(id) ON DELETE CASCADE,
    reporter_user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    reason_code VARCHAR(30) NOT NULL DEFAULT 'other',
    description TEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'open',
    resolution_note TEXT NULL,
    resolved_by BIGINT NULL REFERENCES users(id) ON DELETE SET NULL,
    resolved_at TIMESTAMPTZ NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT event_reports_unique_reporter UNIQUE (event_id, reporter_user_id),
    CONSTRAINT event_reports_reason_check
        CHECK (reason_code IN ('misleading_info','broken_link','spam','scam','duplicate','other')),
    CONSTRAINT event_reports_status_check
        CHECK (status IN ('open','reviewing','resolved','dismissed'))
);

CREATE INDEX IF NOT EXISTS event_reports_status_created_index
    ON event_reports (status, created_at);

CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGSERIAL PRIMARY KEY,
    actor_user_id BIGINT NULL,
    actor_role VARCHAR(20) NULL,
    action VARCHAR(60) NOT NULL,
    target_type VARCHAR(60) NULL,
    target_id BIGINT NULL,
    metadata JSONB NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS audit_logs_actor_index ON audit_logs (actor_user_id);
CREATE INDEX IF NOT EXISTS audit_logs_target_index ON audit_logs (target_type, target_id);
CREATE INDEX IF NOT EXISTS audit_logs_action_index ON audit_logs (action);
CREATE INDEX IF NOT EXISTS audit_logs_created_index ON audit_logs (created_at);
