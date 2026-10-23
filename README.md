# Eloquent Migrations
Use Eloquent migration system without Laravel

```bash
vendor/bin/elmigrator init . # Init the project
vendor/bin/elmigrator create:database MyDatabase # Create a new db
vendor/bin/elmigrator create MyMigration [--table=users] [--create=users] # Create a new migration
vendor/bin/elmigrator migrate # Run all available migrations
vendor/bin/elmigrator rollback # Rollback the last migration. The option --step 3 allows you to rollback multiple migrations
vendor/bin/elmigrator status # Display the migrations status
vendor/bin/elmigrator seed:create MySeed # Create a new seed
vendor/bin/elmigrator seed:run # Run all seeds
```
# Compatibility

| Version    | Illuminate (Eloquent) | PHP    |
|------------|-----------------------|--------|
| 12.2+      | 12.x, 13.x            | >= 8.4 |
| 12.0, 12.1 | 12.x                  | >= 8.4 |
| 11.x       | 11.x                  | >= 8.2 |
| 10.x       | 10.x                  | >= 8.1 |
| 4.x        | 9.x                   | >= 7.4 |

Since 12.2, versions follow [semantic versioning](https://semver.org) and no longer match the Illuminate major version:
supporting a new Illuminate version without breaking change is a minor release, a breaking change (including dropping
an Illuminate or PHP version) is a major release.

# After run init
Will created binary file in directory when running current process. For custom configuration file settings, it is possible to set the environment variable `ELMIGRATOR_CONFIG` or change the binary file to similar:

```php
#!/usr/bin/env php
<?php

if (!isset($_ENV['ELMIGRATOR_CONFIG'])) {
    $_ENV['ELMIGRATOR_CONFIG'] = 'database/elmigrator.php';
}

$app = require vendor/hyde1/eloquent-migrations/app/elmigrator.php';
$app->run();
```

# Run
```bash
export ELMIGRATOR_CONFIG=database/elmigrator.php
migrator create MyMigration [--table=users] [--create=users] # Create a new migration
migrator migrate # Run all available migrations
migrator rollback # Rollback the last migration. The option --step 3 allows you to rollback multiple migrations
migrator status # Display the migrations status
migrator seed:create MySeed # Create a new seed
migrator seed:run # Run all seeds
```
# Development

## Tests

The test suite is split in two PHPUnit suites:

- `tests/Unit`: library classes (`Migration`, `Migrator`, `Seeder`, helpers) and the console application wiring;
- `tests/Integration`: every command run through the console application against a real database, from a
  temporary project directory (config file, migrations and seeders), plus an end-to-end run of `bin/elmigrator`.

```bash
composer test                  # all tests, on SQLite
composer test-unit
composer test-integration
```

Integration tests run on SQLite by default. PostgreSQL and MySQL are provided by `compose.yaml`:

```bash
docker compose up -d --wait
DB_CONNECTION=pgsql composer test
DB_CONNECTION=mysql composer test
```

The connection can be customized with `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` and `DB_PASSWORD`.

Tests asserting a known, not yet fixed, bug are tagged with the `documented-bug` group (`composer test-documented-bugs`).
When a bug is fixed, the group is removed and the assertions are updated to the expected behavior.

## Static analysis and coding standards

```bash
composer lint        # all the checks below
composer phpstan     # PHPStan (level 8)
composer cs-check    # PHP-CS-Fixer (composer cs-fix to fix)
composer phpcs       # PHP_CodeSniffer with Slevomat type hints rules (composer phpcbf to fix)
```

The rules are aligned with the Zelty back-office ones. The `Static analysis` GitHub Actions workflow runs them on
every pull request.

## Testing a new Illuminate version

Before supporting a new Illuminate version, run the test suite against it. The script works in a temporary copy of
the project, `composer.json`, `composer.lock` and `vendor/` are left untouched:

```bash
scripts/test-illuminate.sh ^13.0
DB_CONNECTION=pgsql scripts/test-illuminate.sh dev-master --testsuite=integration
```

The GitHub Actions `Tests` workflow runs the suite on the supported range (lowest and highest dependencies) and on
upcoming Illuminate versions (informational on pull requests, every Monday and on demand with a custom version).
On upcoming versions PHPStan is run as well, to report the Illuminate APIs used by the library or by the test
fixtures (written like project migrations and seeders) that were removed or changed.
