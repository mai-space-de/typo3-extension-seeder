..  include:: /Includes.rst.txt

..  _introduction:

============
Introduction
============

The problem
===========

Content that lives in the database - a set of categories, a default page
tree, demo content, lookup data seeded into a custom table, MM relations
between two domain models - is hard to move between environments. TYPO3
has no built-in equivalent of Laravel's migrations/seeders: ``ext_tables.sql``
only describes *structure*, not content, and hand-written SQL dumps or
manual clicking in the backend do not travel well through version control
or code review.

Mai Seeder fills that gap with **content migrations**: small, versioned PHP
classes that describe how to bring a set of database records into a known
state, run once per environment, and are logged so they are never
accidentally run twice.

Design principles
==================

*   **Framework, not a model layer.** A migration gets a
    :ref:`MigrationContext <migrationcontext>` giving direct access to
    TYPO3's ``ConnectionPool``/``QueryBuilder``. There is no ORM, no TCA
    awareness, no assumption about which columns a table has. This is
    deliberate: the same primitives work identically for ``pages``,
    ``tt_content``, ``sys_file_reference``, a plain custom table, or an MM
    table with no ``uid`` column at all. Establishing the *correct*
    relations between tables is the migration author's responsibility.

*   **Idempotent by construction.** ``uid`` is an auto-increment value and
    is never the same across two environments. Every write helper on
    ``MigrationContext`` (``upsert()``, ``delete()``, ``resolveUid()``)
    matches records by a *lookup key* that the migration author chooses -
    e.g. an ``import_identifier`` column - never by ``uid``. This is what
    makes it safe to run the same migration against a fresh environment and
    a production database that has already diverged.

*   **Tracked, so it can be undone.** Every insert, update and delete made
    through ``MigrationContext`` is recorded in a per-record ledger. A
    reversible migration can call ``$context->revertTrackedChanges()`` in
    its ``down()`` method instead of re-implementing the inverse logic by
    hand.

*   **Zero registration.** Migrations are discovered automatically from
    every active extension's ``Classes/Migrations/`` directory - including
    extensions other than ``mai_seeder`` itself. See
    :ref:`writing-migrations`.

The two log tables
===================

``tx_maiseeder_migration``
    One row per executed (or failed) migration run: identifier, the class
    that was run, batch number, timestamp, success/failure, error message.
    This answers "which migrations ran, and when" - the CLI's
    ``migrate:status`` and the backend module both read from here.

``tx_maiseeder_migration_record``
    One row per database record affected by a migration run: which table,
    which lookup criteria identify the record, whether it was inserted,
    updated or deleted, and (for updates/deletes) a JSON snapshot of the
    row *before* the change. This is what makes rollback possible without
    the migration author having to write bespoke undo logic, and it is
    also what a later migration can use to resolve a ``uid`` created by an
    earlier one.
