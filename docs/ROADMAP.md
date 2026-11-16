# SocialPublicator — Product and Integration Roadmap

**Status date:** 2026-11-15

## Product objective

SocialPublicator is the editorial, campaign and social-account control plane. It manages workspaces, permissions, connected social accounts, destinations, campaign rules, publication scheduling and delivery visibility.

It must remain usable as an independent product. A Dubai-Shop connection is optional and must never be required for local publications, local campaigns or other supported workflows.

## System boundaries

| Application | Owns | Must not own |
|---|---|---|
| Dubai-Shop | Product catalogue, variants, sale prices, publication visibility, warehouses and stock availability | Social credentials, editorial calendar, social destinations or publication execution |
| SocialPublicator | Workspaces, users and permissions, social connections, campaigns, destinations, publication planning, routing and execution records | Dubai-Shop's source-of-truth inventory or browser/session automation |
| WBot | Execution of assigned WhatsApp and Facebook Group tasks, isolated agent instances, browser/session runtime and execution reporting | Product catalogue, campaign selection, workspace billing or official Meta Page OAuth |

### Channel policy

- WhatsApp delivery is executed by WBot using the configured WhatsApp session.
- Facebook Group delivery remains browser-session automation in WBot. It is an unofficial integration and is sensitive to Facebook UI and policy changes.
- Facebook Page delivery uses SocialPublicator's official Meta OAuth / Graph API integration whenever the Page permissions and API capabilities allow it.
- SocialPublicator remains the control plane; WBot remains an execution agent.

## Integration cardinality and tenant isolation

**Mandatory relationship: one Dubai-Shop installation ↔ one SocialPublicator workspace.**

- One Dubai-Shop installation may be paired with exactly one SocialPublicator workspace.
- One SocialPublicator workspace may have at most one Dubai-Shop installation.
- A Dubai-Shop installation must never fan out catalogue, stock or webhook events to multiple workspaces.
- Each integration credential, HMAC identity, event stream, sync cursor and external catalogue mirror belongs to that single pair.
- A workspace without a Dubai-Shop pair continues operating independently.
- Multiple independent stores require separate Dubai-Shop installations and separate workspaces.

Enforce this invariant in database constraints and in the connection/handshake API. Do not rely only on Filament form visibility or application-level checks.

## Current implementation baseline

### Available in SocialPublicator

- Laravel 13 / Filament 5 application with workspace isolation and membership-based permissions.
- Social accounts and connections are separated from publication destinations.
- Account-level and destination-level authorization for managed workspaces.
- Multiple publication destinations per occurrence with execution tracking by occurrence and target.
- WhatsApp group administration verification and filtering of groups available to managed accounts.
- Official Meta OAuth and Facebook Page publication flow.
- Atomic publication execution claims, idempotency and explicit handling of uncertain outcomes.
- Controlled reconciliation/retry for unknown Meta Page outcomes.
- Optional, workspace-scoped Dubai-Shop integration foundation and external catalogue mirror are implemented on the current integration branch; final integration activation is gated on CI/review.

### Available in Dubai-Shop

- Transactional integration outbox and signed HMAC event delivery.
- Read-only integration API for published catalogue items, variants, prices and eligible warehouses.
- Rollout/readiness controls for the SocialPublicator integration.

### Available in WBot

- Isolated multi-instance agent runtime and account-scoped execution.
- WhatsApp via WPPConnect.
- Facebook Group automation through browser sessions.
- Execution claims, idempotency and reporting back to SocialPublicator.
- Administrative account-revocation preflight is proposed in the open integration PR and must be validated against the merged SocialPublicator contract.

## Phase 0 — Close and stabilize current work

1. Validate the SocialPublicator optional Dubai-Shop integration PR, including migrations, tests, formatting and activation gates.
2. Validate SocialPublicator account revocation/reactivation.
3. Validate WBot revocation preflight against the exact SocialPublicator health response contract.
4. Refresh this roadmap and the Dubai-Shop integration operations/readiness documentation.
5. Do not merge a dependent WBot change before the SocialPublicator contract it consumes is available.
6. Merge only after CI is green and the repository owner explicitly approves.

**Exit criteria:** all outstanding PRs are reviewed, their CI corresponds to their latest head commits, dependencies are explicit, and documentation matches the code.

## Phase 1 — Enforce the integration contract

- Enforce the one-installation-to-one-workspace invariant with database uniqueness and server-side authorization.
- Bind installation identity, workspace identity and HMAC key identity into the signed event contract.
- Define stable event IDs, event schema versions, timestamps, aggregate revisions and idempotency behavior.
- Publish product lifecycle events (publish, unpublish and relevant updates), not only price/inventory changes.
- Include an inventory/availability snapshot or monotonic revision in stock events so queued events cannot silently lose intermediate stock transitions.
- Preserve ordering per aggregate and reject stale revisions.
- Add durable inbox recovery for persisted events whose queue dispatch failed; use leases, bounded retries and dead-letter visibility.
- Protect configurable Dubai-Shop API endpoints against SSRF, private/loopback/link-local targets and DNS rebinding; validate redirects as well as initial hosts.
- Define safe HMAC key rotation with an overlap window and key versioning.

**Exit criteria:** the pair is authenticated and unique; event delivery is durable, replay-safe, ordered and observable; catalogue and stock state converge without cross-workspace leakage.

## Phase 2 — Complete campaign routing and channel delivery

- Route Dubai-Shop-origin campaigns to explicitly selected, active and authorized destinations.
- Support WhatsApp Groups through WBot.
- Support Facebook Groups through WBot browser sessions, with explicit operational warnings and health checks.
- Support Facebook Pages through the official Meta publishing flow.
- Reject disconnected, revoked, unverified or unauthorized accounts before creating execution jobs.
- Keep destination selection, content validation and campaign policy in SocialPublicator.
- Keep channel-specific delivery and session state in the appropriate executor.
- Preserve per-destination idempotency and execution history.

**Exit criteria:** a campaign can target one or more eligible destinations, and each destination has independent status, retry/reconciliation behavior and audit history.

## Phase 3 — Cross-repository end-to-end validation

Build automated contract tests across all three applications for:

- Initial installation/workspace pairing and rejection of duplicate pairings.
- Authentication, HMAC signature verification, replay rejection and key rotation.
- Full catalogue sync, pagination and reconciliation.
- Product publish/unpublish, price changes and stock depletion/restock.
- Event retries, duplicate delivery, ordering and out-of-order revisions.
- Campaign dispatch to WhatsApp Groups, Facebook Groups and Facebook Pages.
- Revocation, reconnection and permission changes during queued work.
- Dubai-Shop disconnection while SocialPublicator continues independent operation.
- Failure recovery, dead-letter visibility and audit traceability.

**Exit criteria:** CI validates the real contracts between repositories, not only mocks isolated within each repository.

## Phase 4 — Security, reliability and operations

- Triage WBot npm audit findings; upgrade dependencies in compatibility-tested increments, especially WPPConnect/Puppeteer.
- Make critical dependency/security checks visible and blocking where actionable.
- Add structured correlation IDs across Dubai-Shop events, SocialPublicator inbox/campaigns and WBot executions.
- Add metrics and alerts for outbox lag, inbox lag, sync age, failed dispatches, queue saturation and disconnected agents.
- Define retention, backup, restore and credential-rotation procedures.
- Verify production HTTPS, webhook exposure, rate limits, least-privilege tokens and branch protection.
- Document the operational limitations of unofficial Facebook Group automation.

**Exit criteria:** operators can identify, trace, pause and recover failures without losing events or duplicating publications.

## Phase 5 — Controlled Clásico Dubai pilot

- Use one Dubai-Shop installation paired to one dedicated SocialPublicator workspace.
- Start with a limited catalogue, a small set of warehouses and explicitly approved destinations.
- Keep campaign dispatch disabled until full sync, connection health and end-to-end tests pass.
- Begin with supervised/manual dispatch; expand automation only after measured stability.
- Define rollback: pause campaigns, disable integration, preserve audit/history and continue SocialPublicator independently.

**Exit criteria:** an agreed pilot period completes with no cross-workspace leakage, unexplained catalogue divergence or duplicate publication incidents.

## Phase 6 — Scale and productize

- Support multiple customers by repeating the one-to-one installation/workspace pair, never by sharing one installation across workspaces.
- Add onboarding and guided pairing, diagnostics, per-tenant quotas and operational dashboards.
- Define versioned API/event compatibility and deprecation policies.
- Add tenant-level export, retention and deletion workflows.
- Scale workers and queues by tenant/channel while preserving fairness and isolation.

## Delivery rules

- Work one phase at a time, in a dedicated branch per change.
- Inspect the current repository and its CI before implementation.
- Run relevant tests locally or through CI, fix real failures and rerun.
- Require CI to pass on the latest PR head, not on an earlier commit.
- Do not merge SocialPublicator changes without explicit owner approval.
- Do not start a dependent phase until its prerequisite contract is merged and validated.
- Do not enable production automated campaigns before the Phase 3 and Phase 4 exit criteria are met.
