# DSM SYNC NOTE FOR CLAUDE

Last reconciled: 2026-10-17

Claude: before continuing DSM work, treat this repository as newer than your previous chat/session handoff.

## Canonical baseline

Repository:

`aryhidayattt/dsm`

Active development branch:

`dev`

The repository is now the source of truth for the current project state.

Your previous `/home/claude/run`, `paket-revisi-N.zip`, package-number workflow, and chat memory remain useful history, but they are not the canonical current baseline when they conflict with `dev`.

## Read these first

1. `CLAUDE.md`
2. `doc/ai/PROJECT_RULES.md`
3. `doc/ai/DECISIONS.md`
4. `doc/ai/CURRENT_STATE.md`
5. `doc/ai/HANDOFF.md`
6. `doc/ai/CHANGELOG_AI.md`

## Important changes since your previous handoff

### 1. Git workflow is now ahead of the old ZIP workflow

Current flow:

`dev` -> test/review -> `main` -> separate final transfer to `amienramdhani/dsm_app`.

Do not assume `/home/claude/run` or the latest numbered ZIP is the newest state.

### 2. Secret/config architecture changed

Do not put secrets in tracked `config.php`.

Tracked `config.php` now loads ignored local file:

`config.local.php`

Safe placeholders live in:

`config.local.example.php`

Never commit API keys, PEPPER, passwords, tokens, VAPID private keys, or other credentials.

### 3. Telemarketing KPI design has newer approved decisions

The current approved design is newer than parts of the existing implementation and newer than your old handoff.

Required:

- one indicator per row,
- active/inactive indicator,
- Actual source,
- KPI parameters,
- weight,
- interactive total-weight progress to 100%,
- `Salin ke TM lain`,
- no dominant internal KPI IDs,
- no generic `Aksi` button as the main drill-down pattern.

Current code still contains some older UI patterns. Do not treat those older patterns as the desired design.

### 4. Telemarketing KPI validation has newer approved structure

Required conceptual structure:

- subordinate/other TM detail panel,
- current TM's own KPI validation panel,
- compact one-row KPI information,
- Target / Actual / Achievement / Score where applicable,
- `Rincian` for drill-down,
- detail can show counted dealers, unmet dealers, reasons, and Sales owner.

### 5. Target configuration placement

Target settings belong in the Target area/menu.

Do not move target configuration into System for implementation convenience.

### 6. Form Order remains protected

The current Form Order already covers the intended operational flow.

Do not redesign/rebuild it without an explicitly approved new mockup or instruction.

### 7. Current repository is further ahead in several modules

Verified present in `dev`:

- KPI Khusus v2,
- MTD / N-day DOS basis,
- notification/PWA infrastructure,
- Telemarketing backend and assignments,
- Form Order PDF/WhatsApp output,
- substantial Gemini-based AI Assistant backend.

Check the code before labeling these as missing.

### 8. Known notification mismatch

Your old handoff says all roles may send notifications within scope.

Current code gives Sales and Telemarketing no recipient scope.

Treat this as a known requirement/code mismatch that needs explicit handling, not as proof that the old handoff is current.

### 9. Manual Telemarketing KPI group input is already present

Your old handoff marked the manual `Kelompok KPI` input as UNKNOWN.

Current frontend already uses a manual text input for it.

## Working rule going forward

When your chat memory conflicts with the repository:

1. latest explicit user instruction wins,
2. latest approved mockup/design,
3. `DECISIONS.md`,
4. `CURRENT_STATE.md`,
5. existing business rules,
6. current code.

Before coding, inspect the current `dev` implementation. Do not restore an older design merely because it exists in your previous session memory.

After meaningful work, update `HANDOFF.md` and `CHANGELOG_AI.md`; add only explicitly approved decisions to `DECISIONS.md`.
