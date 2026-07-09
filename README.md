# Mai Seeder

Laravel-inspired, versioned **content migrations** for TYPO3 v13/v14: seed, update and clean up records - and the relations between them - in any table, whether it's TCA-registered or not. Every run is logged, so you always know which migration ran, when, and whether it succeeded.

This extension does **not** manage database schema - that's still `ext_tables.sql` and TYPO3's own `vendor/bin/typo3 database:updateschema`. Mai Seeder starts once your tables exist and is concerned purely with their *content*.

## Quick example

```php
// EXT:my_extension/Classes/Migrations/Migration20260709120000CreateProductCategoryRelation.php
final class Migration20260709120000CreateProductCategoryRelation extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Seeds the "Shoes" category and links it to a product.';
    }

    public function up(MigrationContext $context): void
    {
        $categoryUid = $context->upsert(
            table: 'tx_myext_domain_model_category',
            lookup: ['import_identifier' => 'cat-shoes'],
            values: ['title' => 'Shoes'],
        );

        $productUid = $context->resolveUid('tx_myext_domain_model_product', ['import_identifier' => 'prod-sneaker-42']);

        $context->upsert(
            table: 'tx_myext_category_product_mm',
            lookup: ['uid_local' => $categoryUid, 'uid_foreign' => $productUid],
            values: ['sorting' => 1],
        );
    }
}
```

```bash
vendor/bin/typo3 mai-seeder:make:migration "create product category relation" --extension=my_extension
vendor/bin/typo3 mai-seeder:migrate
vendor/bin/typo3 mai-seeder:migrate:status
```

No registration needed - any active extension's `Classes/Migrations/` directory is scanned automatically. `upsert()`/`delete()`/`resolveUid()` always match by a caller-chosen lookup key, never by `uid`, which is what makes seeding safe to re-run and portable across environments.

There's also an admin-only backend module ("System > Content Migrations") for running and rolling back migrations, modelled after the Install Tool's Upgrade Wizards.

Full documentation: [`Documentation/Index.rst`](Documentation/Index.rst).
