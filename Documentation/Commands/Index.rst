..  include:: /Includes.rst.txt

..  _commands:

========
Commands
========

``mai-seeder:migrate``
    Runs all pending migrations (oldest identifier first), stopping at the
    first failure.

    ..  option:: --dry-run

        Runs migrations inside a transaction that is rolled back
        afterwards - nothing is persisted, but the migration code actually
        executes, so this also surfaces runtime errors.

    ..  option:: --step=<n>

        Only run at most ``n`` pending migrations.

    ..  option:: --force

        Skip the confirmation prompt shown when running in a production
        application context.

``mai-seeder:migrate:status``
    Lists every discovered migration with its status (pending / executed /
    failed), batch number, execution timestamp and whether it is
    reversible.

..  _commands-migrate-rollback:

``mai-seeder:migrate:rollback``
    Rolls back the last executed batch by default.

    ..  option:: --step=<n>

        Roll back the ``n`` most recently executed migrations, across
        batch boundaries.

    ..  option:: --batch=<n>

        Roll back every migration in a specific batch.

``mai-seeder:migrate:reset``
    Rolls back every executed migration, batch by batch. Asks for
    confirmation unless ``--force`` is given.

``mai-seeder:migrate:refresh``
    Equivalent to ``migrate:reset`` followed by ``migrate``. Asks for
    confirmation unless ``--force`` is given.

..  _commands-make-migration:

``mai-seeder:make:migration``
    Scaffolds a new migration class.

    ..  code-block:: bash

        vendor/bin/typo3 mai-seeder:make:migration "create product category relation" \
            --extension=my_extension \
            --reversible

    ..  option:: name

        Required argument. A descriptive name, converted to StudlyCase for
        the class name.

    ..  option:: --extension=<key>

        Required. The extension key whose ``Classes/Migrations/``
        directory the file is created in (created if it does not exist
        yet).

    ..  option:: --reversible

        Also implement :php:`ReversibleMigrationInterface`, with a
        ``down()`` stub calling :php:`$context->revertTrackedChanges()`.
