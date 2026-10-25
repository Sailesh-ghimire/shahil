# Workspace CRM — delivery tracker

Source of truth for scope: the plan artifact, **Draft 3** (25 Oct 2026). Drafts 1–2 are superseded.

Status values: **Todo** · **Partial** · **Complete** · **Blocked** (needs something from outside the codebase).

| Phase | Name | Status |
| --- | --- | --- |
| 0 | Foundation | Complete |
| 1 | Customers | Complete (awaiting your spreadsheet test) |
| 2 | Sales | Complete |
| 3 | Productivity and insight | Complete |
| 4 | Messaging | Complete (live Meta test skipped) |
| 5 | Hardening | Complete |

## Phase 0 — Foundation

Done when: two workspaces exist and tests prove neither can see the other.

| # | Task | Status | Notes |
| --- | --- | --- | --- |
| 0.1 | Docker environment: app (PHP 8.5 + nginx), MySQL 8.4, Redis, Mailpit, Horizon, scheduler | Complete | `compose.yaml`, `docker/` |
| 0.2 | Laravel 13 + Filament 5 + Spatie permission 8 + activitylog 5 + Horizon + Pest + Larastan | Complete | Reverb deferred: not installable on Laravel 13 yet (guzzle psr7 v3 conflict); inbox will poll every 10 s as the plan allows |
| 0.3 | Filament panel at `/app/{workspace}` with `Team` tenancy, registration, create workspace, switcher, profile | Complete | Login, sign-up, password reset, email verification, profile with optional app-based 2FA |
| 0.4 | Workspace scoping: `BelongsToWorkspace` trait (global scope + auto `team_id`), workspace context for jobs/commands | Complete | `app/Models/Concerns/BelongsToWorkspace.php`, `app/Support/Workspace/CurrentWorkspace.php` (`run($team, fn)` for jobs) |
| 0.5 | Spatie teams mode, 34 permissions, `CreateDefaultRoles`, `SetPermissionsTeam` tenant middleware, Owner `Gate::before` | Complete | Spatie team id is read from the workspace context via a custom team resolver, so the two never disagree |
| 0.6 | Members screen (invite, change role, remove) and Roles screen (permission checklist grouped by module) | Complete | Settings → Members, Roles; Owner role read-only; a workspace always keeps one Owner |
| 0.7 | Email invitations (accept link joins the workspace with a role) | Complete | 7-day link; sign-up with an invited email joins automatically |
| 0.8 | Notification defaults: top-right position, database notifications (bell) | Complete | Top right; bell polls every 30 s |
| 0.9 | Isolation test harness, Pest, Larastan, Pint, CI workflow | Complete | `tests/Feature/Workspaces`, `phpstan.neon` (level 6, clean), `.github/workflows/ci.yml` (not run yet: no remote repo) |
| 0.10 | Demo seed data (Acme Traders, Himalaya Exports from the panel preview) | Partial | Users, workspaces, roles seeded; business records added with each phase |
| 0.11 | Register Meta app and start Business Verification | Blocked | Needs the business owner's Meta account; not code |

## Phase 1 — Customers

Done when: you import your real spreadsheet and the failed-rows report makes sense.

| # | Task | Status | Notes |
| --- | --- | --- | --- |
| 1.1 | Companies resource (list, filters, view page with tabs) | Complete | Tabs: Overview, People, Timeline, History; soft delete with 30-day restore |
| 1.2 | Contacts with multiple emails/phones/handles (E.164) and multiple companies with job titles | Complete | Phones stored in E.164; lookup by any format |
| 1.3 | Activities timeline + Log activity on contacts, companies (leads, deals in Phase 2) | Complete | Shared Timeline tab + Log activity; leads and deals get it in Phase 2 |
| 1.4 | Tags | Complete | Created inline from any tags field; filter on lists |
| 1.5 | Import CSV + XLSX (XLSX converted to CSV), duplicate rule, failed-rows file; export CSV/XLSX | Complete | Duplicate rule: update / skip / create; report shows created, updated, skipped, failed |
| 1.6 | Audit log (activitylog with `team_id`), Settings → Audit log, History tab | Complete | Settings → Audit log; History tab on records (audit.view only) |

## Phase 2 — Sales

Done when: a lead goes from New to Won entirely by clicking.

| # | Task | Status | Notes |
| --- | --- | --- | --- |
| 2.1 | Pipelines and stages (repeater, probability, kind open/won/lost); default pipeline per workspace | Complete | Settings → Pipelines; exactly one Won and one Lost stage; a stage with deals can’t be removed |
| 2.2 | Products + categories (SKU unique per workspace, 13% VAT default) | Complete | Catalog → Products (+ categories, import/export, active toggle with undo) |
| 2.3 | Leads resource + Leads board (New, Contacted, Qualified, Unqualified) | Complete | Board uses Livewire 4 `wire:sort` (SortableJS underneath); Unqualified asks for a reason; round-robin owner |
| 2.4 | Convert lead → contact, company, deal (`ConvertLead`) | Complete | Matches an existing contact by email/phone before creating one |
| 2.5 | Deals resource with line items; amount = sum of lines | Complete | Line total = qty × price − discount (before tax); deal value = sum |
| 2.6 | Deals board (SortableJS), won/lost prompts, undo notification, stale flag, 25 cards/column | Complete | Won asks close date, Lost asks reason, undo in the notification, stale after 7 days, phone dropdown |

## Phase 3 — Productivity and insight

Done when: a reminder set for 5 minutes ahead arrives on time in your timezone.

| # | Task | Status | Notes |
| --- | --- | --- | --- |
| 3.1 | Tasks resource (Today, Overdue, Upcoming, Completed) + Add follow-up on any record | Complete | Engage → Tasks & follow-ups; Tasks tab + Add follow-up on contacts, companies, leads, deals |
| 3.2 | Reminder dispatch every minute (bell + email), mark done / snooze | Complete | `crm:send-reminders` every minute; bell + email with signed Mark done / Snooze links; persistent toast while the panel is open (30 s poll) |
| 3.3 | Stale-deal rule creates follow-ups | Complete | `crm:stale-deal-follow-ups` hourly; days set in Workspace settings (0 = off) |
| 3.4 | Dashboard widgets | Complete | Follow-ups today, new leads, my pipeline, won this month (sparklines) |
| 3.5 | Reports page with filters + 12 widgets | Complete | 12 widgets incl. first reply time by channel; reps locked to own numbers; 10-min cache |

## Phase 4 — Messaging

Done when: a real WhatsApp message creates a lead and the reply reaches the phone.

| # | Task | Status | Notes |
| --- | --- | --- | --- |
| 4.1 | Channel accounts + Channels settings page (connect / disconnect / reconnect) | Complete | Settings → Channels; connect with account id + token (checked with Meta); Embedded Signup once the Meta app is approved |
| 4.2 | Webhook endpoints (signature check, raw payload log, queue, 200 fast) | Complete | `/webhooks/meta/{channel}` (whatsapp, facebook, instagram); HMAC check, raw payload kept 14 days, queued, 200 at once |
| 4.3 | Inbound processing: dedupe, workspace lookup, contact match or auto-lead, media download | Complete | De-duplicated by Meta message id; WhatsApp matches contacts by phone; new senders become round-robin leads |
| 4.4 | Inbox page (filters, thread, reply, assign, create lead, link contact) | Complete | Engage → Inbox; polls every 10 s (Reverb not installable yet) |
| 4.5 | Outbound send + status webhooks; WhatsApp 24 h window → templates | Complete | Sent / delivered / read ticks; after 24 h WhatsApp only allows synced templates (Meta charges) |
| 4.6 | Messenger and Instagram drivers | Complete | Same driver interface; Messenger/Instagram replies after 24 h use the HUMAN_AGENT tag |
| 4.7 | Token health job | Complete | `crm:check-channel-tokens` daily; flags Reconnect needed and notifies channel managers |
| 4.8 | Live test with real Meta accounts | Blocked | Skipped for now (your call). Needs Meta app id/secret, a WhatsApp test number and its token |

## Phase 5 — Hardening

| # | Task | Status | Notes |
| --- | --- | --- | --- |
| 5.1 | Large seeded dataset + performance pass (indexes, list < 500 ms at 50k contacts) | Complete | `crm:seed-load-test` (50k contacts, 100k methods, 25k leads, 25k deals, 100k activities) + `crm:benchmark`; new indexes, prefix contact search. Production image: every page 89–392 ms |
| 5.2 | Security review | Complete | Independent review: 2 high, 4 medium, 3 low — all fixed with regression tests (`tests/Feature/SecurityTest.php`) |
| 5.3 | Backups, error monitoring | Complete | `backup` service: daily gzipped dump, 14 days kept; failed jobs and Horizon long waits emailed to `OPS_ALERT_EMAIL`; Horizon gated by `HORIZON_ADMINS` |
| 5.4 | User guide | Complete | `docs/USER_GUIDE.md`; `README.md` (setup, production image, restore) |

## Decisions taken (open questions from the plan, answered with the plan's assumptions)

1. Contacts "multiple" = both several emails/phones and several companies.
2. Workspaces: SaaS-ready, self-registration on (can be switched to invite-only).
3. Leads are separate from contacts, with a Convert step.
4. Each workspace connects its own Meta accounts.
5. One currency per workspace, NPR default; money stored as integer paisa.
6. Several pipelines supported.
7. Lead assignment: round-robin with manual override.
8. Reps see colleagues' records, edit only their own.
9. No billing in the first release.
