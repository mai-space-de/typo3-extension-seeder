..  include:: /Includes.rst.txt

..  _writing-migrations:

==================
Writing migrations
==================

Where migrations live
======================

Create a class in your extension's ``Classes/Migrations/`` directory. It
must implement :php:`Maispace\MaiSeeder\Migration\MigrationInterface` -
extending :php:`AbstractMigration` is the easiest way to do that. No
registration is needed: every active extension's ``Classes/Migrations/``
directory is scanned automatically (see
:ref:`how discovery works <discovery>` below).

You can scaffold a new migration with the
:ref:`make:migration <commands-make-migration>` command:

..  code-block:: bash

    vendor/bin/typo3 mai-seeder:make:migration "create product category relation" --extension=my_extension

This creates a class named after the current timestamp, e.g.
``Classes/Migrations/Migration20260709120000CreateProductCategoryRelation.php``.
The timestamp prefix is what :php:`AbstractMigration::getIdentifier()`
parses to determine execution order - keep the ``Migration<YmdHis><Name>``
naming pattern, or override :php:`getIdentifier()` yourself.

A minimal migration
=====================

..  code-block:: php

    <?php

    declare(strict_types=1);

    namespace MyVendor\MyExtension\Migrations;

    use Maispace\MaiSeeder\Migration\AbstractMigration;
    use Maispace\MaiSeeder\Migration\MigrationContext;

    final class Migration20260709120000CreateProductCategoryRelation extends AbstractMigration
    {
        public function getDescription(): string
        {
            return 'Seeds the "Shoes" category and links it to two products.';
        }

        public function up(MigrationContext $context): void
        {
            $categoryUid = $context->upsert(
                table: 'tx_myext_domain_model_category',
                lookup: ['import_identifier' => 'cat-shoes'],
                values: ['title' => 'Shoes'],
            );

            $productUid = $context->resolveUid(
                'tx_myext_domain_model_product',
                ['import_identifier' => 'prod-sneaker-42'],
            );

            if ($productUid === null) {
                // referenced product does not exist (yet) on this environment - skip or throw
                return;
            }

            $context->upsert(
                table: 'tx_myext_category_product_mm',
                lookup: ['uid_local' => $categoryUid, 'uid_foreign' => $productUid],
                values: ['sorting' => 1],
            );
        }
    }

..  _migrationcontext:

The MigrationContext API
==========================

``upsert(table, lookup, values = [])``
    Inserts a row if none matches ``lookup``, or updates the matching row
    otherwise. Always matches by ``lookup``, **never** by ``uid`` - this is
    what makes seeding safe to re-run and portable across environments,
    since auto-increment ``uid`` values differ between environments.
    Returns the record's ``uid``, or :php:`null` if the table has no
    ``uid`` column (e.g. a plain MM table).

``delete(table, lookup)``
    Deletes every row matching ``lookup``, after snapshotting each one so
    it can be restored by :php:`revertTrackedChanges()`. Returns the
    number of deleted rows.

``resolveUid(table, lookup)``
    Looks up the ``uid`` of a record by a lookup key, typically one
    established by an earlier migration's ``upsert()`` call (in the same
    extension or a different one). Always reads current database state, so
    it is safe to call regardless of migration run order.

``revertTrackedChanges()``
    Undoes every change this migration made, in reverse order: inserted
    rows are deleted, deleted rows are restored, updated rows are restored
    to their previous values. Call this from ``down()`` when there is no
    more specific rollback logic needed - see below.

``getConnection(table)`` / ``getQueryBuilder(table)``
    Escape hatches for anything not covered above, e.g. bulk reads or
    conditions ``upsert()``/``delete()`` cannot express. ``getQueryBuilder()``
    returns a TYPO3 :php:`QueryBuilder` with all default restrictions
    (enable-fields etc.) removed, since migrations operate below the TCA
    abstraction.

Deleting content
==================

``up()`` is not limited to inserting data - removing stale content is just
as legitimate a migration:

..  code-block:: php

    public function up(MigrationContext $context): void
    {
        $context->delete('tx_myext_domain_model_product', ['import_identifier' => 'prod-discontinued-1']);
    }

Reversible migrations
========================

Implement :php:`ReversibleMigrationInterface` in addition to
:php:`MigrationInterface` (in practice: add a ``down()`` method) only when
rolling back genuinely makes sense. Not every content migration is - and
Mai Seeder does not force you to pretend otherwise. In the common case, a
:php:`down()` only needs to undo what :php:`up()` tracked:

..  code-block:: php

    use Maispace\MaiSeeder\Migration\ReversibleMigrationInterface;

    final class Migration20260709120000CreateProductCategoryRelation extends AbstractMigration implements ReversibleMigrationInterface
    {
        // ... getDescription(), up() as above ...

        public function down(MigrationContext $context): void
        {
            $context->revertTrackedChanges();
        }
    }

Migrations that only implement :php:`MigrationInterface` show up as
"not reversible" in the backend module and cannot be targeted by
``migrate:rollback`` - attempting to do so raises
:php:`IrreversibleMigrationException`.

Skipping a migration conditionally
=====================================

Override :php:`shouldRun()` (default: :php:`true`) to opt out at runtime,
e.g. based on extension configuration or the application context:

..  code-block:: php

    public function shouldRun(): bool
    {
        return !\TYPO3\CMS\Core\Core\Environment::getContext()->isTesting();
    }

..  _discovery:

How discovery works
======================

There is no registration file. When migrations are run, every active
extension's ``Classes/Migrations/`` directory is scanned for classes
implementing :php:`MigrationInterface`; the class's fully qualified name is
resolved from the PSR-4 autoload prefix the extension's own
``composer.json`` registers for its ``Classes/`` directory. This means any
extension - not just ``mai_seeder`` - can ship migrations simply by adding
a class in the right place.

Migration identifiers must be unique across *all* active extensions;
a collision raises :php:`DuplicateMigrationIdentifierException` (this
should only happen if two migrations happen to share the exact same
timestamp and name, or a custom :php:`getIdentifier()` collides).

Things to keep in mind
=========================

*   **Empty lookup criteria are rejected.** :php:`upsert()`, :php:`delete()`
    and :php:`resolveUid()` throw :php:`\InvalidArgumentException` if
    ``lookup`` is empty, since matching "every row" is almost never
    intended.
*   **One failure halts the run.** ``migrate``/``migrate:rollback`` stop at
    the first failing migration rather than continuing past it, so later
    migrations never run against a database a failed migration left
    half-written.
*   **Transactions cover the "Default" connection only.** Each migration
    runs inside a transaction on TYPO3's default database connection - this
    is also what makes ``--dry-run`` possible for arbitrary migration code
    (the migration actually runs, and the transaction is rolled back
    instead of committed). If a migration writes to a different,
    explicitly configured connection (rare multi-database setups), those
    writes are not covered by this guarantee.
