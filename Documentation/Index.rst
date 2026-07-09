..  include:: /Includes.rst.txt

..  _start:

==========
Mai Seeder
==========

:Extension key:
    ``mai_seeder``

:Package name:
    ``maispace/mai-seeder``

:Version compatibility:
    TYPO3 13.4 LTS, TYPO3 14

Laravel-inspired, versioned **content migrations** for TYPO3: seed, update
and clean up records - and the relations between them - in any table,
whether it is TCA-registered or not. Every run is logged, so you always
know which migration ran, when, and whether it succeeded.

..  important::

    This extension does **not** manage database schema. Creating tables and
    columns is still the job of ``ext_tables.sql`` and TYPO3's own database
    schema comparison (``vendor/bin/typo3 database:updateschema``). Mai
    Seeder starts once your tables already exist and is concerned purely
    with their *content* - the records inside them, and the relations
    between records across tables (including MM tables).

..  toctree::
    :maxdepth: 2
    :titlesonly:

    Introduction/Index
    Installation/Index
    WritingMigrations/Index
    Commands/Index
    BackendModule/Index
