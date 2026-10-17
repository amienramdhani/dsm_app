# DSM AI CHANGELOG

Records meaningful changes performed with AI assistance.

This is not a replacement for Git commit history.

---

## Format

### YYYY-MM-DD - Short title

AI:
Claude / ChatGPT / Other

Scope:
What area was changed.

Files:
- file1
- file2

Changes:
- change
- change

Validation:
- test performed
- result

Pending:
- unresolved item if any

---

# History

## 2026-10-17 - Admin focus, KPI compact, and portfolio transfer implementation

AI:
ChatGPT

Scope:
Admin UI / Edit User / KPI Khusus / portfolio ownership transfer

Files:
- `api.php`
- `assets/searchable.js`
- `assets/admin_revision.css`
- `assets/admin_revision.js`
- `assets/admin_portfolio_guard.js`
- `assets/portfolio_transfer.js`
- `lib/dealer_brand.php`
- `lib/portfolio_core.php`
- `lib/api_portfolio.php`

Changes:
- Added the approved Admin category structure with a dedicated Upload Harian group while retaining Sistem.
- Added Admin Focus Mode; inactive panels are dimmed and the active panel uses a soft version of its group color.
- Polished Edit User and made Region portofolio rangkap blank/disabled when rangkap Sales is off.
- Added the four-stage Alihkan Portofolio flow: Penerima, Bagi Dealer, Target & Aktual, and Preview.
- Supports multiple receiving Sales, per-dealer allocation, bulk assignment, and dealer-brand ownership preservation.
- Moves and merges active/future targets from Target_Input, Target_Periode, and manual KPI Khusus SO target storage.
- Current-period ST attribution is rebuilt from effective dealer-brand ownership without rewriting the original uploaded raw source.
- Added atomic rollback coverage around portfolio transfer data changes.
- Added Edit User guard so an active rangkap portfolio cannot be switched off directly without the portfolio transfer flow.
- Added compact KPI Khusus accordion/focus editing, weight progress treatment, and a visible fixed rule `Ada SO tapi stok 0 = 0` in the editor.

Validation:
- Changes were written only to branch `dev`.
- `main` and Amin's final repository were not touched.
- Repository compare confirms `dev` is ahead of the prior reconciliation baseline and the expected changed/new files are present.
- Local Laragon runtime validation is still required after pulling `dev`.

Pending:
- Run smoke tests in Laragon for Admin navigation, Edit User, KPI Khusus, and a non-production portfolio-transfer scenario before merging anywhere else.

---

## 2026-10-17 - Claude handoff reconciled with current repository

AI:
ChatGPT

Scope:
AI context synchronization / repository documentation

Files:
- `CLAUDE.md`
- `doc/ai/CURRENT_STATE.md`
- `doc/ai/CLAUDE_SYNC.md`
- `doc/ai/HANDOFF.md`
- `doc/ai/CHANGELOG_AI.md`

Changes:
- Reconciled Claude's previous final DSM handoff against the current `dev` branch and approved decisions.
- Declared `aryhidayattt/dsm` branch `dev` as the canonical current development state.
- Added `CURRENT_STATE.md` with verified repository state and known documentation/code drift.
- Added `CLAUDE_SYNC.md` explaining what Claude's previous session is behind on.
- Updated `CLAUDE.md` so Claude must read current repository context before coding.
- Removed the obsolete pending item about connecting ChatGPT to GitHub.
- Documented that the old numbered ZIP/package workflow is historical rather than canonical.
- Documented the newer secret model: tracked `config.php` loads ignored `config.local.php`.
- Documented current Telemarketing KPI and KPI Validation decisions that are newer than parts of the existing implementation.
- Documented known notification permission mismatch.
- Documented that the AI Assistant backend in the current repository is materially ahead of the older handoff description.

Validation:
- Compared Claude handoff against the current `dev` code and repository AI docs.
- Verified current KPI Khusus v2 code, PWA/push code, Telemarketing modules, Form Order implementation, config separation, and AI Assistant backend are present in the repository.
- No application source code was modified during this synchronization.

Pending:
- Future application changes should use `dev` plus current AI documentation as baseline.
- Known implementation gaps remain to be addressed only when explicitly requested.

---

## 2026-10-17 - Development repository initialized

AI:
ChatGPT

Scope:
Git / development workflow

Changes:

- Created private DSM development repository.
- Created separate `main` and `dev` branches.
- `main` defined as stable baseline.
- `dev` defined as active development branch.
- Local secret configuration separated from tracked configuration.
- `config.local.php` excluded from Git.
- `config.local.example.php` added as safe configuration template.
- Development repository kept separate from Amin's final DSM repository.

Validation:

- Git working tree clean.
- `dev` successfully pushed to `origin/dev`.
- `config.local.php` is ignored by Git.
