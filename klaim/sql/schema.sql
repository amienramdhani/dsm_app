CREATE TABLE claim_users (
 id {AI},
 username VARCHAR(40) NOT NULL UNIQUE,
 pass_hash VARCHAR(100) NOT NULL,
 name VARCHAR(80) NOT NULL,
 job VARCHAR(30) NOT NULL,
 nik VARCHAR(30) NULL,
 region VARCHAR(60) NULL,
 entity VARCHAR(80) NULL,
 roles VARCHAR(80) NOT NULL DEFAULT '',
 can_submit INT NOT NULL DEFAULT 0,
 bank VARCHAR(40) NULL, bank_no VARCHAR(40) NULL, bank_name VARCHAR(80) NULL,
 can_petty INT NOT NULL DEFAULT 0,
 petty_ceiling BIGINT NOT NULL DEFAULT 0,
 meal_rate BIGINT NULL,
 sign_img {LTXT} NULL,
 wa VARCHAR(20) NULL,
 active INT NOT NULL DEFAULT 1,
 created_at DATETIME NOT NULL
);
CREATE TABLE claim_entitlement (
 id {AI}, user_id INT NOT NULL, category VARCHAR(30) NOT NULL, enabled INT NOT NULL DEFAULT 1, budget BIGINT NOT NULL DEFAULT 0,
 UNIQUE (user_id, category)
);
CREATE TABLE claim_types (
 id {AI}, category VARCHAR(30) NOT NULL, name VARCHAR(60) NOT NULL, active INT NOT NULL DEFAULT 1, locked INT NOT NULL DEFAULT 0, sort_no INT NOT NULL DEFAULT 0
);
CREATE TABLE claim_lookup (
 id {AI}, kind VARCHAR(20) NOT NULL, val VARCHAR(80) NOT NULL, UNIQUE (kind, val)
);
CREATE TABLE claim_gate (
 id {AI}, name VARCHAR(80) NOT NULL UNIQUE, used INT NOT NULL DEFAULT 1
);
CREATE TABLE claim_rules (
 id {AI}, effective_from DATE NOT NULL,
 nota_max_days INT NULL, max_cost_km BIGINT NULL, max_ent_pax BIGINT NULL, km_diff_pct INT NULL,
 bbm_round BIGINT NULL, pc_grace_days INT NULL, min_km_mobil INT NULL, min_km_motor INT NULL, days VARCHAR(400) NULL, max_no_nota INT NULL, min_km_makan INT NULL,
 created_by INT NULL, created_at DATETIME NOT NULL
);
CREATE TABLE claim_settings (
 skey VARCHAR(40) PRIMARY KEY, sval VARCHAR(200) NULL
);
CREATE TABLE claim_seq (
 skey VARCHAR(60) PRIMARY KEY, n INT NOT NULL DEFAULT 0
);
CREATE TABLE claims (
 id {AI},
 number VARCHAR(40) NULL UNIQUE,
 claimant_id INT NOT NULL, created_by INT NOT NULL,
 region VARCHAR(60) NULL, entity VARCHAR(40) NULL, entity_by INT NULL, type_ids VARCHAR(200) NULL,
 category VARCHAR(30) NOT NULL, type_id INT NOT NULL,
 brand VARCHAR(60) NULL, refnote VARCHAR(80) NULL, location VARCHAR(80) NULL,
 total BIGINT NOT NULL DEFAULT 0,
 status VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
 stage VARCHAR(12) NULL,
 route {LTXT} NULL,
 requires_hos INT NOT NULL DEFAULT 0, hos_reason {LTXT} NULL,
 outside_sales INT NOT NULL DEFAULT 0,
 budget_period VARCHAR(7) NULL, avail_before BIGINT NULL, over_budget_amount BIGINT NOT NULL DEFAULT 0,
 rule_set_id INT NULL,
 current_version INT NOT NULL DEFAULT 0,
 meta {LTXT} NULL,
 acct_bank VARCHAR(40) NULL, acct_no VARCHAR(40) NULL, acct_name VARCHAR(80) NULL, acct_own INT NOT NULL DEFAULT 1, acct_reason VARCHAR(500) NULL,
 ba_required INT NOT NULL DEFAULT 0,
 rev_stage VARCHAR(12) NULL, rev_holder VARCHAR(12) NULL, rev_by INT NULL, rev_note {LTXT} NULL, rev_notes {LTXT} NULL,
 admin_check {LTXT} NULL,
 stage_since DATETIME NULL, submitted_at DATETIME NULL, done_at DATETIME NULL,
 pc_id INT NULL,
 created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL
);
CREATE INDEX ix_claims_status ON claims(status, stage);
CREATE INDEX ix_claims_claimant ON claims(claimant_id);
CREATE TABLE claim_versions (
 id {AI}, claim_id INT NOT NULL, version_no INT NOT NULL, total BIGINT NOT NULL, reason {LTXT} NULL, snapshot {LTXT} NULL, created_at DATETIME NOT NULL
);
CREATE TABLE claim_notes (
 id {AI}, claim_id INT NOT NULL, line_no INT NOT NULL DEFAULT 1, nominal BIGINT NOT NULL DEFAULT 0, nota_date DATE NULL, detail {LTXT} NULL
);
CREATE TABLE claim_files (
 id {AI}, uploader INT NOT NULL, stored_name VARCHAR(80) NOT NULL, orig VARCHAR(160) NULL, mime VARCHAR(60) NULL, size_b INT NOT NULL DEFAULT 0, sha VARCHAR(64) NOT NULL,
 ref_type VARCHAR(10) NULL, ref_id INT NULL, note_id INT NULL, slot VARCHAR(20) NULL, created_at DATETIME NOT NULL
);
CREATE INDEX ix_files_ref ON claim_files(ref_type, ref_id);
CREATE INDEX ix_files_sha ON claim_files(sha);
CREATE TABLE claim_approvals (
 id {AI}, ref_type VARCHAR(10) NOT NULL, ref_id INT NOT NULL, version_no INT NOT NULL DEFAULT 1, stage VARCHAR(12) NOT NULL,
 user_id INT NOT NULL, action VARCHAR(12) NOT NULL, notes {LTXT} NULL, created_at DATETIME NOT NULL
);
CREATE TABLE claim_history (
 id {AI}, ref_type VARCHAR(10) NOT NULL, ref_id INT NOT NULL, actor_id INT NULL, actor_name VARCHAR(80) NULL, role VARCHAR(30) NULL,
 action VARCHAR(200) NOT NULL, notes {LTXT} NULL, before_val VARCHAR(500) NULL, after_val VARCHAR(500) NULL, created_at DATETIME NOT NULL
);
CREATE INDEX ix_hist_ref ON claim_history(ref_type, ref_id);
CREATE TABLE claim_ba (
 id {AI}, number VARCHAR(40) NOT NULL UNIQUE, claim_id INT NOT NULL UNIQUE, creator_id INT NOT NULL, creator_role VARCHAR(12) NOT NULL,
 triggers {LTXT} NULL, explanation {LTXT} NULL, snapshot {LTXT} NULL, extra {LTXT} NULL, version_no INT NOT NULL DEFAULT 1, created_at DATETIME NOT NULL
);
CREATE TABLE claim_ba_sign (
 id {AI}, ba_id INT NOT NULL, version_no INT NOT NULL, role VARCHAR(12) NOT NULL, user_id INT NOT NULL, signed_at DATETIME NOT NULL, img {LTXT} NULL, UNIQUE (ba_id, version_no, role)
);
CREATE TABLE claim_transfers (
 id {AI}, ref_type VARCHAR(10) NOT NULL, ref_id INT NOT NULL, transfer_no VARCHAR(60) NOT NULL, amount BIGINT NOT NULL, user_id INT NOT NULL, entity VARCHAR(40) NULL, created_at DATETIME NOT NULL
);
CREATE TABLE claim_pc (
 id {AI}, number VARCHAR(40) NULL UNIQUE, claimant_id INT NOT NULL, region VARCHAR(60) NULL, entity VARCHAR(40) NULL,
 purpose VARCHAR(200) NOT NULL, remark {LTXT} NULL, location VARCHAR(80) NULL, p_start DATE NOT NULL, p_end DATE NOT NULL, amount BIGINT NOT NULL,
 plan {LTXT} NULL, status VARCHAR(20) NOT NULL, stage VARCHAR(12) NULL, route {LTXT} NULL,
 rev_stage VARCHAR(12) NULL, rev_holder VARCHAR(12) NULL, rev_note {LTXT} NULL,
 acct_bank VARCHAR(40) NULL, acct_no VARCHAR(40) NULL, acct_name VARCHAR(80) NULL,
 realized BIGINT NOT NULL DEFAULT 0, sisa BIGINT NOT NULL DEFAULT 0, setoran_no VARCHAR(60) NULL, setoran_amount BIGINT NULL, excess_claim_id INT NULL,
 stage_since DATETIME NULL, funded_at DATETIME NULL, nota_sent_at DATETIME NULL, done_at DATETIME NULL,
 created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL
);
CREATE TABLE claim_pc_notes (
 id {AI}, pc_id INT NOT NULL, nominal BIGINT NOT NULL, nota_date DATE NOT NULL, plan_idx INT NULL, off_plan INT NOT NULL DEFAULT 0, uraian VARCHAR(160) NULL
);
CREATE TABLE claim_notif (
 id {AI}, user_id INT NOT NULL, kind VARCHAR(20) NOT NULL, text VARCHAR(1000) NOT NULL, ref_type VARCHAR(10) NULL, ref_id INT NULL, is_read INT NOT NULL DEFAULT 0, created_at DATETIME NOT NULL
);
CREATE INDEX ix_notif_user ON claim_notif(user_id, is_read);
CREATE TABLE claim_backup (
 id {AI}, role VARCHAR(12) NOT NULL, code_hash VARCHAR(100) NOT NULL, issuer_id INT NOT NULL, for_user_id INT NULL, used_by INT NULL, used_at DATETIME NULL, expires_at DATETIME NOT NULL, created_at DATETIME NOT NULL,
 to_user_id INT NULL, duration_min INT NULL, active_until DATETIME NULL, use_count INT NULL, max_use INT NULL, last_used_at DATETIME NULL, revoked_at DATETIME NULL, revoked_by INT NULL
);
CREATE TABLE claim_export_log (
 id {AI}, user_id INT NOT NULL, kind VARCHAR(20) NOT NULL, filter_json VARCHAR(500) NULL, row_count INT NOT NULL, created_at DATETIME NOT NULL
);

CREATE TABLE claim_fuel_price (
 id {AI}, effective_from DATE NOT NULL, pertalite INT NOT NULL, pertamax INT NOT NULL, created_by INT NOT NULL, created_at DATETIME NOT NULL
);
