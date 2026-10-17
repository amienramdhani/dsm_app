# DSM CURRENT STATE

Last reconciled: 2026-10-17

## Canonical Source

The canonical current project state is:

- Repository: `aryhidayattt/dsm`
- Active development branch: `dev`

External AI chat memory, old ZIP/package notes, `/home/claude/run`, and older handoff documents are supporting history only.

If an external handoff conflicts with the repository, use the repository plus the latest explicit user instruction and approved decisions.

---

## Current Technical Baseline

DSM is currently a PHP + MySQL/MariaDB application with PWA support.

The repository contains, among others:

- `index.php`
- `api.php`
- `lib/*.php`
- `assets/*.js`
- `assets/telemarketing/*`
- `sw.js`
- `panduan-content.js`
- `doc/ai/*`

### Local secret configuration

Secrets are no longer stored in tracked `config.php`.

Tracked `config.php` only loads:

`config.local.php`

`config.local.php` is ignored by Git and must never be committed.

Safe placeholders live in:

`config.local.example.php`

This supersedes older instructions that treated `config.php` itself as the server secret file.

---

## Verified Existing Modules in `dev`

The following are present in the current repository code.

### KPI Khusus v2

Current code contains:

- ST / SO / DA / DOS indicators,
- `_kk_config`, `_kk_rule`, `_kk_target`,
- replace-or-add KPI group behavior,
- DOS default points `[[0,10],[60,0],[91,-5]]`,
- stock 0 = 0 points,
- stock without SO = -5 points,
- MTD / N-day DOS basis,
- SO target support.

### Notifications / PWA

Current code contains:

- PWA service worker `dsm-v14`,
- push notification handler,
- notification image/tag/url support,
- notification message/log/rule tables,
- scheduled and automatic notification infrastructure,
- `notif_cli.php` cron runner.

Runtime behavior on the real HTTPS server, Android, iPhone, and cron still requires real-environment validation.

### Telemarketing backend and operational modules

Current code contains:

- Telemarketing profile and dealer-brand assignment,
- period scope,
- separate Telemarketing KPI storage,
- manual actual audit log,
- Follow Up records,
- Form Order records,
- Telemarketing business entity data,
- ranking/KPI endpoints,
- order PDF/text frontend logic.

### Form Order

Current frontend supports:

- business entity,
- date,
- region,
- ref note,
- CBD / COD / TOP,
- master or manual/NOO dealer,
- Sales Person,
- manual item entry,
- Brand / Item / Color / Qty / Price,
- Subtotal,
- Credit Note,
- TOTAL,
- PDF preview/download,
- WhatsApp text output.

### AI Assistant

`lib/api_ai.php` contains a substantial read-only AI assistant implementation using Gemini tool/function routing plus local fallback behavior.

The code contains tools for areas such as:

- performance summary,
- brand achievement,
- early warning,
- dealer not ordering,
- dealer priority,
- dealer potential,
- DOS risk,
- KPI detail,
- top type,
- weekly trend,
- glossary.

Presence in code does not mean runtime/completeness has been fully validated.

---

## Latest Approved Decisions That Are Ahead of Existing Code

The repository documentation is intentionally allowed to be ahead of implementation.

### Telemarketing KPI UI

Approved direction in `DECISIONS.md` is newer than some current UI code.

Required direction:

- one indicator per row,
- active/inactive control,
- Actual source,
- KPI parameters,
- weight,
- interactive total-weight progress toward 100%,
- `Salin ke TM lain`,
- human-readable UI,
- internal IDs must not dominate the interface.

Current code still contains UI elements such as a generic `Aksi`/`Aturan` column and `Salin kebijakan bulan sebelumnya`, so this area is not yet fully aligned with the latest approved design.

### Telemarketing KPI validation

Latest approved direction:

- panel for subordinate/other TM details,
- panel for the current TM's own KPI validation,
- compact one-row KPI information,
- Target / Actual / Achievement / Score where relevant,
- use `Rincian`, not generic `Aksi`,
- drill-down can show counted dealers, unmet dealers, reason, and Sales owner.

Older handoff text does not fully capture this latest design.

### Target placement

Target configuration belongs in the Target area/menu and must not be moved to System merely for implementation convenience.

---

## Known Reconciliation Findings

### Notification permission mismatch

An older Claude handoff states that all roles may send notifications within their scope.

Current notification code gives Sales and Telemarketing an empty recipient scope, so that requirement is not currently implemented as written.

Treat this as a known mismatch, not as a new requirement change.

### Telemarketing KPI group input

An older handoff marks manual text input for KPI Group as unknown/not built.

Current frontend already uses a manual input field for `Kelompok KPI`.

Therefore that specific point is implemented in the current repository.

### AI Assistant status

Older handoff text describes the AI backend as partial/unknown and mentions an OpenAI option.

Current repository contains a substantial Gemini-based `lib/api_ai.php` implementation.

Runtime validation is still required, but the repository is materially ahead of the older handoff description.

### Legacy package workflow

Package numbers such as 22/23/24 remain useful historical references, but they are no longer the active development workflow.

Active development now happens through Git:

`dev` -> testing/review -> `main` -> separate final transfer to Amin's `dsm_app` repository.

---

## Current Documentation Authority

Use these files together:

1. `PROJECT_RULES.md` — working rules.
2. `DECISIONS.md` — approved product/UI/business decisions.
3. `CURRENT_STATE.md` — verified current repository state and known drift.
4. `HANDOFF.md` — current active work and pending items.
5. `CHANGELOG_AI.md` — meaningful AI-assisted changes.
6. `CLAUDE_SYNC.md` — concise sync note for Claude.

Never assume an older external handoff is newer than these files.
