# Project Standards

> Adopted 2026-10-31 at the owner's request. Referenced from `CLAUDE.md` → *Coding
> Conventions*. Where this page and `CLAUDE.md` overlap, the stricter rule wins, and
> `CLAUDE.md`'s Part 1 (RULE #0–#2) outranks both. See *How this fits with CLAUDE.md*
> at the end for the points that need reading together.

## Purpose
This project must remain clean, organized, understandable, maintainable, and scalable.
Every change should look like it was written by an experienced human engineer.

## Before coding
- Read the relevant existing code and documentation before making changes.
- Follow the existing architecture and patterns unless a change is explicitly approved.
- Prefer small, focused changes over large rewrites.
- Do not refactor unrelated code while fixing a bug or adding a feature.

## Code quality
- Write clear, descriptive names; no vague names such as `data`, `temp`, `handleStuff`, or `utils2`.
- Keep functions and components small and focused on one responsibility.
- Avoid duplication; extract shared logic only when the behavior is genuinely the same.
- Separate UI, business logic, data access, validation, and API communication.
- Avoid deep nesting; use early returns and small helper functions.
- Remove dead code, unused imports, debug logs, and commented-out code.
- Do not introduce dependencies without approval.
- Do not use `any` in TypeScript; use precise types.
- Handle expected errors explicitly with useful, user-safe messages.
- Never hardcode secrets, API keys, URLs, or environment-specific values.

## Scalability requirements
All new code and modifications must be designed to scale safely as users, data, features, traffic, and team members grow.

### Design principles
- Prefer modular, loosely coupled components with clear boundaries.
- Keep business logic independent from UI, database, and external services.
- Use clear service/repository/domain layers where the project already supports them.
- Design APIs and data models to be extensible without breaking existing clients.
- Avoid global state and hidden dependencies.
- Make asynchronous or long-running work queue-based where appropriate.
- Design for horizontal scaling: avoid local file storage, in-memory sessions, or single-instance assumptions unless explicitly justified.
- Use pagination, filtering, sorting, and limits for any list endpoint that could grow.
- Use database indexes and efficient queries for frequently accessed data.
- Avoid N+1 queries; batch or join data intentionally.
- Use caching only when it provides a clear benefit, and define invalidation rules.
- Make configuration environment-driven so deployments can scale independently.

### Scalability review
Before completing any feature or refactor, assess:
1. What happens at 10x current users, records, requests, and files?
2. What are the likely bottlenecks: database, API, memory, storage, third-party service, or frontend rendering?
3. Can this module be extended without modifying unrelated modules?
4. Are there missing indexes, unbounded queries, unbounded loops, or unbounded file uploads?
5. Is the solution simple now but not locked into a dead-end architecture?

### Scalability tradeoffs
- Do not over-engineer.
- Do not add microservices, queues, caching layers, sharding, or complex infrastructure unless current scale, clear bottlenecks, or a defined roadmap requires it.
- Choose the simplest scalable design.
- Document any scalability limitation and the planned future upgrade path.

## File organization
- Place code in the module/folder that matches its responsibility.
- One primary responsibility per file where practical.
- Use the project’s existing naming conventions.
- Keep related tests next to the code or in the established test folder.

## Documentation
- Maintain documentation in `docs/`.
- Every major module must have a short Markdown document explaining:
  - Its purpose.
  - Main files.
  - Data flow.
  - External services or APIs used.
  - Important rules and limitations.
  - Scalability assumptions and future upgrade path.
- Important files must begin with a brief header comment linking to the relevant documentation.
- Update documentation whenever behavior, architecture, APIs, workflows, or scalability assumptions change.

## Testing and verification
- Before finishing, run:
  - Formatter
  - Linter
  - Typechecker
  - Build
  - Relevant tests
- Add or update tests for changed business logic.
- Check changed database queries for pagination, limits, indexes, and N+1 patterns.
- Do not claim completion if any verification command fails.

## Git and delivery
- Use small, descriptive commits.
- Use this commit format: `type(scope): short description`
- Examples:
  - `refactor(auth): split login service from controller`
  - `feat(orders): add order status validation`
  - `perf(orders): paginate order list and add customer index`
  - `docs(api): document checkout endpoints`
- Summarize every completed task: files changed, behavior impact, scalability impact, documentation updates, and verification results.

## Safety rules
Always ask before:
- Changing public APIs.
- Changing database schemas or migrations.
- Changing authentication, authorization, payments, or security logic.
- Installing dependencies.
- Adding queues, caching, background workers, or other infrastructure.
- Deleting substantial code.
- Rewriting an entire module.

## How this fits with CLAUDE.md
The points where the two documents meet, so neither is misread:

- **Commits.** "Small, descriptive commits" describes their *shape*. `CLAUDE.md` still
  says nothing is committed or pushed unless the owner asks; shipping is
  `scripts/ship.sh`.
- **Commands on this box.** The staging box is live. Formatter: `vendor/bin/pint`.
  Static analysis: `composer analyse` (PHPStan). Build: `npm run build`, run as
  `actionsrunner`. Tests: `config:clear` first, then `vendor/bin/phpunit <file>` or
  `scripts/test-per-file.sh`, then `config:cache`, all run as `www-data`, never root.
- **Known gaps** (from the 2026-10-31 audit; until they are fixed, a failure here
  must be compared with its pre-existing state, not taken as "all green"):
  - `phpstan-baseline.neon` names deleted files, so `composer analyse` errors before
    analysing.
  - Some existing files already fail Pint.
  - `tests/known-failures.txt` lists 12 tests that fail today.
- **TypeScript.** There is none yet; the `any` rule applies to any that is added
  (e.g. React islands).
- **Header comments vs Design Rule #3.** Headers go on files you *create or
  substantially change*. They are not a reason to add docblocks to untouched code.
- **Module docs.** Most modules have no `docs/` page yet; write one when a module
  is next changed substantially. Reference so far: `docs/uploads.md`.
- **Data safety.** Before any migration or data change, take a verified backup first
  (`CLAUDE.md` RULE #2). The database is shared with production.
