# DSM PROJECT RULES

## Project

DSM = Distribution Sales Monitoring.

This is an existing production-oriented application, not a greenfield project.

Primary objective:

> Implement exactly what is requested while preserving everything that is already correct.

---

## Core Rule

Use:

**Minimal Change / Maximum Preservation**

Do not:

- redesign unrelated pages,
- move menus without instruction,
- rename existing fields without need,
- change business rules without approval,
- add generic buttons/actions without request,
- remove existing functionality,
- refactor large areas when a local patch is enough.

---

## Requirement Priority

When requirements conflict, follow this order:

1. Latest explicit user instruction.
2. Latest approved mockup/design.
3. Approved decisions in `DECISIONS.md`.
4. Existing DSM business rules.
5. Existing implementation.
6. Technical best practice.

Never override a higher-priority rule with a lower-priority assumption.

---

## UI / UX

DSM UI must remain:

- clean,
- compact,
- professional,
- information-dense but readable,
- usable on desktop and mobile.

Avoid:

- excessive cards,
- excessive borders,
- oversized controls,
- tiny mobile text,
- unnecessary decorative UI.

Mobile tables may use horizontal scrolling rather than shrinking content until unreadable.

---

## Roles

DSM may contain roles including:

- Sales
- Supervisor
- ASM
- RGM
- Head of Sales
- Top Tier / COO / CCO / BOD
- Telemarketing
- Admin
- Super Admin

Never broaden data visibility merely to simplify implementation.

Existing role and permission rules must be preserved.

---

## KPI

Do not change KPI formulas or calculation sources unless explicitly requested.

KPI weight configuration must total 100%.

Telemarketing KPI and Sales KPI are related but not identical systems.

Telemarketing KPI actual may come from:

- official Sales transactions filtered by TM assignment,
- manual Admin actual for configured indicators.

---

## Telemarketing

TM may handle:

- multiple Sales,
- multiple Regions,
- cross ASM/RGM assignments,
- selected dealers,
- Brand-based assignments.

The same dealer may have different TM assignments for different Brands.

TM ranking remains separate from Sales ranking.

---

## Data Safety

Never commit:

- API keys
- passwords
- PEPPER
- tokens
- private keys
- OAuth secrets
- service-account credentials

Local secrets belong in ignored local configuration.

`config.local.php` must remain excluded from Git.

Do not destructively modify database schema unless explicitly required.

Prefer additive/backward-compatible changes.

---

## Code Changes

Before implementation:

1. inspect existing code,
2. identify affected files/functions,
3. identify dependencies,
4. preserve unrelated behavior.

After implementation, verify:

- requested feature works,
- existing feature remains intact,
- desktop remains usable,
- mobile remains usable,
- role permissions remain correct,
- no secrets were introduced,
- no unrelated files were modified.

---

## Mockups

If the user asks for a mockup first:

DO NOT treat the mockup request as permission to implement production code.

Once a mockup is approved, treat it as a design contract.

Implementation must not materially diverge from the approved mockup without approval.

---

## Git Workflow

Repository:

`aryhidayattt/dsm`

Branches:

- `main` = stable baseline
- `dev` = active development

Active AI development happens in `dev`.

Final production handoff to Amin's repository is a separate step.