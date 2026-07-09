..  include:: /Includes.rst.txt

..  _installation:

============
Installation
============

..  code-block:: bash

    composer require maispace/mai-seeder

Then activate the extension (Extension Manager, or
``vendor/bin/typo3 extension:activate mai_seeder`` if you are using
classic/non-composer mode).

Mai Seeder ships its own two database tables (see
:ref:`introduction <introduction>`). Create them with TYPO3's regular
schema migration command:

..  code-block:: bash

    vendor/bin/typo3 database:updateschema

From here on, migrations are picked up automatically from every active
extension's ``Classes/Migrations/`` directory - there is nothing else to
configure. Continue with :ref:`writing-migrations`.
