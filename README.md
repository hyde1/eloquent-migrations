# Eloquent Migrations
Use Eloquent migration system without Laravel

```bash
vendor/bin/elmigrator init . # Init the project
vendor/bin/elmigrator create MyMigration [--table=users] [--create=users] # Create a new migration
vendor/bin/elmigrator migrate # Run all available migrations
vendor/bin/elmigrator rollback # Rollback the last migration. The option --step 3 allows you to rollback multiple migrations
```
