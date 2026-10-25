# Workspace CRM

A multi-workspace CRM on Laravel 13, Filament 5 and PHP 8.5: companies and contacts, leads and deals boards,
products, tasks with reminders, reports, and one inbox for WhatsApp, Instagram and Facebook.

- What users can do: [docs/USER_GUIDE.md](docs/USER_GUIDE.md)
- Delivery status, phase by phase: [docs/PHASES.md](docs/PHASES.md)

## Run it locally (Docker only)

```bash
cp .env.example .env            # then set APP_KEY below
docker compose up -d --build    # app, MySQL, Redis, Mailpit, Horizon, scheduler, backups
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan filament:assets
```

| Service | Address |
| --- | --- |
| App | http://localhost:8080 (demo: `aarav@acme.example` / `password`) |
| Mailpit (emails) | http://localhost:8025 |
| MySQL | `localhost:3307`, user `crm` / `secret` |
| Horizon (queues) | http://localhost:8080/horizon (emails in `HORIZON_ADMINS`) |

## Everyday commands

```bash
docker compose exec app php artisan test --compact                 # all tests (MySQL crm_testing)
docker compose exec app php artisan test --compact --filter=Inbox  # one test or file
docker compose exec app vendor/bin/pint --dirty --format agent     # code style
docker compose exec app vendor/bin/phpstan analyse                  # Larastan, level 6
docker compose exec app php artisan crm:seed-load-test             # 50,000-contact "Load Test" workspace
docker compose exec app php artisan crm:benchmark load-test        # time the main pages
```

## Production

Build the image with the code baked in (OPcache on, no bind mount):

```bash
docker build -f docker/php/Dockerfile.prod -t workspace-crm:latest .
```

Run it with the same MySQL/Redis setup, a `horizon` and a `scheduler` container from the same image
(`php artisan horizon`, `php artisan schedule:work`), and `php artisan optimize && php artisan filament:optimize`
at start. Set `APP_ENV=production`, `APP_DEBUG=false`, mail settings, `OPS_ALERT_EMAIL`, `HORIZON_ADMINS` and
the `META_*` values. Point Meta's webhooks at `https://<your-domain>/webhooks/meta/{whatsapp|facebook|instagram}`.

Backups: the `backup` service dumps the database daily to `storage/backups` and keeps 14 days.
Restore: `gunzip -c storage/backups/crm-<date>.sql.gz | docker compose exec -T mysql mysql -uroot -psecret crm`.

Measured with 50,000 contacts in the production image: every main page renders in 90–390 ms.
