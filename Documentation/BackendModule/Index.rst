..  include:: /Includes.rst.txt

..  _backend-module:

==============
Backend module
==============

*System > Content Migrations* (admin-only, modelled after the Install
Tool's Upgrade Wizards) lists every discovered migration alongside its
status:

*   **Pending** migrations show an *Execute* button that runs just that one
    migration.
*   **Executed** migrations that implement :php:`ReversibleMigrationInterface`
    show a *Roll back* button.
*   **Failed** migrations are flagged, with the error message available via
    ``migrate:status`` on the CLI.
*   Migrations without ``down()`` are marked "not reversible" and offer no
    rollback action.

Both actions ask for confirmation before running, since they write to the
database. Under the hood, the module calls the exact same
:php:`Maispace\MaiSeeder\Migration\MigrationRunner` service the CLI
commands use - there is no separate code path or separate log.

The module is intentionally admin-only: running a content migration is an
irreversible-by-default database write, comparable in impact to the core
Upgrade Wizards it is modelled after.
