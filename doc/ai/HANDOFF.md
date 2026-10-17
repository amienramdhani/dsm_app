# DSM CURRENT HANDOFF

Last updated: 2026-10-17

## Repository

Development repository:

`aryhidayattt/dsm`

Stable branch:

`main`

Active development branch:

`dev`

The `dev` branch is the canonical current development state.

External AI chat memory, old ZIP packages, and `/home/claude/run` are supporting history only when they conflict with the repository.

---

## Current Status

- Initial DSM development baseline has been committed and pushed.
- ChatGPT GitHub integration is connected and can read/write the private DSM development repository.
- Shared AI context is stored in Git so Claude, ChatGPT, and human development can stay synchronized.
- Local secrets are separated from tracked configuration.
- `config.local.php` is ignored by Git.
- Safe example configuration exists in `config.local.example.php`.
- Development repository remains separate from Amin's final repository: `amienramdhani/dsm_app`.
- Claude's previous final handoff has been reconciled against the current `dev` code and current approved decisions.

---

## Current Workflow

Development:

DSM local / AI development
→ `aryhidayattt/dsm`
→ branch `dev`
→ test / review

After approval:

`dev`
→ `main`
→ final review
→ transfer/finalize into Amin's `dsm_app` repository.

Do not use the old numbered ZIP-package flow as the canonical development workflow anymore.

---

## Shared AI Context

Read in this order before meaningful development work:

1. `CLAUDE.md`
2. `doc/ai/PROJECT_RULES.md`
3. `doc/ai/DECISIONS.md`
4. `doc/ai/CURRENT_STATE.md`
5. `doc/ai/HANDOFF.md`
6. `doc/ai/CHANGELOG_AI.md`

For Claude specifically, also read:

`doc/ai/CLAUDE_SYNC.md`

---

## Important Current Reconciliation Findings

### Telemarketing KPI UI

Approved decisions are ahead of some current UI code.

Latest approved direction requires:

- one indicator per row,
- active/inactive indicator,
- Actual source,
- KPI parameters,
- weight,
- interactive progress toward 100%,
- `Salin ke TM lain`,
- no dominant internal IDs,
- no generic `Aksi` button as the main drill-down pattern.

Current code still contains some older patterns, so the existing UI must not be treated as the final approved design.

### KPI Validation

Latest approved Telemarketing validation structure is:

- subordinate/other TM detail panel,
- current TM's own validation panel,
- compact one-row KPI information,
- Target / Actual / Achievement / Score where relevant,
- `Rincian` drill-down,
- dealer counted/unmet/reason/Sales owner where relevant.

### Notification permission mismatch

Older handoff says all roles may send notifications within scope.

Current code gives Sales and Telemarketing no recipient scope.

This is a known code/requirement mismatch and must not be silently assumed resolved.

### Config architecture

Older instructions referring to secrets inside tracked `config.php` are obsolete.

Tracked `config.php` loads ignored `config.local.php`.

Never commit secrets.

### AI Assistant

Current repository contains a substantial Gemini-based read-only AI Assistant backend. Runtime/completeness still requires validation, but the repository is materially ahead of the older handoff description.

---

## Important Safety

Do not commit:

- `config.local.php`
- API keys
- DSM PEPPER
- credentials
- passwords
- private keys
- tokens

`storage/cache` currently remains versioned because the deployment may depend on generated JSON files.

Do not remove it from Git until the runtime/deployment dependency is confirmed.

---

## Current Priority

Before new large DSM changes:

1. use the current `dev` branch as baseline,
2. preserve approved decisions,
3. do not restore older Claude-session UI where newer decisions exist,
4. implement only requested changes,
5. update AI documentation after meaningful work.

---

## Pending

- Continue DSM development from `dev`.
- Reconcile implementation gaps against the latest approved Telemarketing KPI/validation design when the user requests coding.
- Validate notification runtime/permissions on the actual environment when requested.
- Update this document after meaningful development sessions.
