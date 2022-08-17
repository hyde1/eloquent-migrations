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
