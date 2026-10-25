# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

Multi-workspace CRM (Laravel 13, Filament 5 / Livewire 4, PHP 8.5, MySQL 8.4, Redis + Horizon). Scope comes
from the plan artifact **Draft 3**; delivery status is tracked phase by phase in `docs/PHASES.md` (update it
when a task's status changes). User-facing behaviour is described in `docs/USER_GUIDE.md`.

## Commands (everything runs in Docker; there is no host PHP)

```bash
docker compose up -d                                              # app :8080, mysql :3307, mailpit :8025, horizon, scheduler, backup
docker compose exec -T app php artisan test --compact            # full suite (MySQL db crm_testing, RefreshDatabase)
docker compose exec -T app php artisan test --compact tests/Feature/Sales/LeadToWonTest.php
docker compose exec -T app php artisan test --compact --filter="takes a lead"
docker compose exec -T app vendor/bin/pint --dirty --format agent
docker compose exec -T app vendor/bin/phpstan analyse --memory-limit=1G   # Larastan level 6, must stay clean
docker compose exec -T app php artisan migrate:fresh --seed      # demo data: aarav@acme.example / password
docker compose exec -T app php artisan filament:assets           # after changing resources/css/crm.css
```

The project folder is a Windows bind mount and file access from containers is slow; prefer narrow test runs.
In Git Bash, `sed` replacement strings eat backslashes — use the Edit tool for PHP `use` lines and namespaces.

## Architecture

- **Workspaces = `App\Models\Team`** (Filament tenant, URL `/app/{slug}`). Isolation is done by
  `App\Models\Concerns\BelongsToWorkspace` (global scope + fills `team_id`), which reads
  `App\Support\Workspace\CurrentWorkspace`. That context falls back to Filament's tenant, so it also holds in
  import/export jobs. Jobs, webhooks and commands must wrap work in `CurrentWorkspace::run($team, fn)`.
  Filament's own tenant scoping is **disabled** (`Resource::scopeToTenant(false)` in `AppServiceProvider`):
  its observer re-assigns records created for another workspace. Never add `withoutGlobalScopes()` on
  business queries unless the id already identifies the workspace (e.g. `ChannelAccount::findForWebhook`).
- **Permissions**: spatie/laravel-permission in teams mode; the Spatie team id is resolved from the same
  workspace context (`WorkspaceTeamResolver`). 34 permissions + 5 default roles live in
  `App\Support\Permissions\PermissionCatalog`. Policies extend `App\Policies\ModulePolicy`
  (`module.update.own` vs `.any`, owner/assignee aware). Owner passes everything via `Gate::before` except
  editing the Owner role; only Owners may grant/remove Owner (`OwnerGuard`). Roles are looked up with
  `Role::forTeam()`; Members/Roles/Invitations resources scope by hand in `getEloquentQuery()`.
- **Business rules live in single-purpose actions** (`app/Actions/{Workspaces,Sales,Messaging}`) called by
  both Filament and jobs: `ConvertLead`, `MoveDealToStage`, `ChangeLeadStatus`, `AssignLeadOwner`,
  `ReceiveInboundMessage`, `SendChannelMessage`, `ConnectChannelAccount`.
- **Filament layout rule**: every resource keeps its form in `Schemas/`, its table in `Tables/`, and relation
  manager tables in `RelationManagers/Tables/`; the Resource class only wires them. Shared pieces:
  `app/Filament/{Actions,Forms,Tables,RelationManagers}` (Timeline, History, Deals, Follow-ups tabs).
- **Boards** (`LeadsBoard`, `DealsBoard`) are custom pages using Livewire 4 `wire:sort`; styles in
  `resources/css/crm.css` (registered as a Filament asset). Modal actions there take browser-supplied ids:
  always re-authorize.
- **Money** is integer minor units (`App\Support\Money`, `MoneyInput`); times are stored UTC and shown in the
  user's timezone (`FilamentTimezone`). Phones are E.164 (`App\Support\PhoneNumber`).
- **Messaging**: `routes/webhooks.php` (no session/CSRF, HMAC-checked) → `ProcessMetaWebhook` job →
  channel drivers in `app/Integrations/Meta/Drivers` (WhatsApp, Messenger, Instagram behind
  `ChannelDriver`). Outbound via `DeliverChannelMessage`; media downloads only from Meta hosts
  (`GraphClient::MEDIA_HOSTS`). Inbox UI: `App\Filament\Pages\Inbox` (polls every 10 s; Reverb is not
  installable on Laravel 13 yet).
- **Scheduler** (`routes/console.php`): reminders every minute, stale-deal follow-ups hourly, channel token
  check daily, pruning (soft deletes after 30 days, webhook logs after 14).
- **Reports**: `App\Support\Reports\{ReportFilters,SalesMetrics}` (SQL grouping, 10-min cache per workspace +
  filters); widgets in `app/Filament/Widgets/Reports`.

## Testing conventions

Pest + `pest-plugin-livewire`. `Tests\TestCase` has `createWorkspace()`, `memberOf($team, $role)` and
`actingInWorkspace($user, $team)` (sets panel, tenant and Spatie team). Webhook fixtures:
`Tests\Support\MetaPayloads`; HTTP to Meta is always `Http::fake()`d. `tests/Feature/DemoDataPagesTest.php`
renders every page against the demo seed — add new pages there. Every new resource needs an isolation test
(other workspace's records invisible, URLs 404).

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.5. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.
- Activate the `deploying-to-cloud` skill whenever deploying to Laravel Cloud, configuring Cloud environments or resources, using the Cloud CLI, or troubleshooting Cloud deployments.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.
- After the feature tests pass, ask the user to run the complete suite with `php artisan test --compact`.

</laravel-boost-guidelines>
