# Database migrations

Doctrine Migrations manages schema changes; it does not back up content or uploaded files.
Baseline: `DoctrineMigrations\Version20260927022452` (28 tables, including Messenger).
Generated from current metadata on 2026-09-27 after `doctrine:schema:validate` confirmed the existing database was in sync.
The SQL targets MariaDB (the platform detected locally). Review platform compatibility before deploying elsewhere.

## PowerShell setup

```powershell
Set-Location C:\xampp\htdocs\thiengia
$php = 'C:\xampp81\php\php.exe'
```

Confirm DATABASE_URL points to the intended database. Never commit credentials or SQL backups.
Take a full database backup using your database administration tool and verify restoration on an isolated database before adoption/deployment.
Do not run `doctrine:schema:update --force` once migrations are in use.

## Existing database: adopt, DO NOT execute the baseline

1. Back up the database and verify the backup.
2. Run the validation below. STOP on mapping errors or schema differences; review differences separately. Do not blindly mark versions executed.
3. Initialize the migration tracking table and mark ONLY the baseline version executed. These commands write migration metadata, not CMS content.

```powershell
& $php bin/console doctrine:schema:validate
& $php bin/console doctrine:schema:update --dump-sql --complete
# Continue ONLY if schema validation succeeds and no unexpected SQL is reported.
& $php bin/console doctrine:migrations:sync-metadata-storage
& $php bin/console doctrine:migrations:version 'DoctrineMigrations\Version20260927022452' --add
& $php bin/console doctrine:migrations:status
& $php bin/console doctrine:migrations:list
```

Do not use `version --add --all`: that can falsely mark future migrations executed.
The initial setup implementation does not automatically adopt the baseline on any existing database.

## New, empty database

Create a dedicated empty database and point DATABASE_URL to it. Do not reuse the populated CMS database.

```powershell
& $php bin/console doctrine:migrations:sync-metadata-storage
& $php bin/console doctrine:migrations:migrate --dry-run
& $php bin/console doctrine:migrations:migrate
& $php bin/console doctrine:schema:validate
```

The baseline refuses existing application tables before queuing SQL. Its rollback is intentionally disabled to prevent dropping the entire CMS.
MariaDB DDL is not transactional. A failed migration may leave partial changes: inspect and recover from the verified backup rather than blindly retrying.
The baseline creates schema only; it does not seed admin accounts, settings or content.

## Subsequent changes

After baseline adoption, change Entity mappings, then:

```powershell
& $php bin/console doctrine:migrations:diff
# Review the generated PHP/SQL, especially DROP, renamed columns and data conversions.
& $php bin/console doctrine:migrations:migrate --dry-run
& $php bin/console doctrine:migrations:migrate
& $php bin/console doctrine:schema:validate
```

Test on an isolated restored copy before production. Commit migrations together with Entity changes, composer.json/composer.lock, symfony.lock and configuration.
Do not edit an already deployed migration; add a new version. Data migrations need their own review and backup plan.
For production commands use the correct environment (`--env=prod --no-debug`) and rebuild the Symfony cache after deployment.

## Non-destructive smoke test

```powershell
& $php tests/Smoke/migrations_baseline.php
```

This validates queued baseline SQL, existing-schema protection and blocked rollback without connecting to a database.
