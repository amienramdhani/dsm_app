# DSM - Claude Project Instructions

## Canonical baseline

The current canonical DSM development state is the repository:

`aryhidayattt/dsm`

Active development branch:

`dev`

Do not assume an older Claude chat, `/home/claude/run`, numbered ZIP package, or previous handoff is newer than the repository.

If your previous session memory conflicts with the repository or approved decisions, the repository context below wins unless the user explicitly says otherwise.

## Before making ANY code change in DSM

1. Read `doc/ai/PROJECT_RULES.md`.
2. Read `doc/ai/DECISIONS.md`.
3. Read `doc/ai/CURRENT_STATE.md`.
4. Read `doc/ai/HANDOFF.md`.
5. Read `doc/ai/CLAUDE_SYNC.md`.
6. Check the existing `dev` implementation before modifying code.
7. Never override an approved decision without explicit user instruction.
8. Make the smallest possible change required.
9. Do not redesign unrelated UI.
10. Do not remove existing features unless explicitly requested.
11. Preserve desktop, mobile, role, permission, and existing business logic.
12. Never commit credentials, API keys, passwords, PEPPER, tokens, private keys, or local secrets.

## Requirement priority

When information conflicts, use this order:

1. Latest explicit user instruction.
2. Latest approved mockup/design.
3. `doc/ai/DECISIONS.md`.
4. `doc/ai/CURRENT_STATE.md`.
5. Existing DSM business rules.
6. Current `dev` implementation.
7. Older external handoff/chat memory.

Do not restore an older implementation merely because it exists in your previous session memory.

## After completing meaningful work

1. Update `doc/ai/HANDOFF.md`.
2. Add an entry to `doc/ai/CHANGELOG_AI.md`.
3. If repository state materially changed, update `doc/ai/CURRENT_STATE.md`.
4. If a new product/business decision was explicitly approved, add it to `doc/ai/DECISIONS.md`.

## Important

- `main` = stable baseline.
- `dev` = active development.
- Development work must happen on `dev`.
- Final production transfer to Amin's `amienramdhani/dsm_app` repository is a separate step.
- Do not push directly to Amin's final repository unless explicitly instructed.
- Tracked `config.php` loads ignored `config.local.php`; secrets belong only in local/server configuration and must never be committed.

## Current DSM revision handoff — read before coding

For the current revision package based on `dev` baseline `76cb12b6b5e334ccf0e6ace68fb29df4a4538bb2`, also read these four documents in order:

1. `docs/claude-handoff/peta-sistem.md`
2. `docs/claude-handoff/matriks-role.md`
3. `docs/claude-handoff/revisi-bug.md`
4. `docs/claude-handoff/request-revisi.md`

Rules for these documents:

- `peta-sistem.md` maps the current system from code. `DUGAAN` and `TIDAK DITEMUKAN` must remain treated as uncertain.
- `matriks-role.md` maps observed role behavior from code; do not convert uncertain cells into assumptions.
- `revisi-bug.md` contains baseline bugs only.
- `request-revisi.md` contains requested changes only. Do not merge bug and request definitions.
- Existing DSM on the baseline is the visual/structural source of truth unless a request explicitly changes it.
- Before coding, audit the actual runtime/load path and map the files/functions that will be touched.
- Do not claim a request is complete based only on static checks; verify the relevant role, desktop/mobile behavior, backend authorization, data effects, and regression risk.
- Do not merge `backup-dev-20261018` wholesale. It may be used only as an audit/reference source.
