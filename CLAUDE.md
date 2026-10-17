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
