# Eloquent Migrations
Use Eloquent migration system without Laravel

```bash
vendor/bin/elmigrator init . # Init the project
vendor/bin/elmigrator create MyMigration [--table=users] [--create=users] # Create a new migration
vendor/bin/elmigrator migrate # Run all available migrations
vendor/bin/elmigrator rollback # Rollback the last migration. The option --step 3 allows you to rollback multiple migrations
vendor/bin/elmigrator status # Display the migrations status
vendor/bin/elmigrator seed:create MySeed # Create a new seed
vendor/bin/elmigrator seed:run # Run all seeds
```
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