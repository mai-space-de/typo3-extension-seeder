#
# Table structure for the migration run log.
# One row per executed (or failed) migration.
#
CREATE TABLE tx_maiseeder_migration (
	uid int(11) unsigned NOT NULL auto_increment,
	identifier varchar(191) DEFAULT '' NOT NULL,
	migration_class varchar(255) DEFAULT '' NOT NULL,
	description text,
	batch int(11) DEFAULT 0 NOT NULL,
	success tinyint(1) DEFAULT 1 NOT NULL,
	error_message text,
	execution_time_ms int(11) DEFAULT 0 NOT NULL,
	executed_at int(11) DEFAULT 0 NOT NULL,

	PRIMARY KEY (uid),
	UNIQUE KEY identifier (identifier)
);

#
# Table structure for the per-record change ledger.
# One row per database record affected by a migration run, used to
# resolve relations across migrations and to allow reverting a
# migration's changes (down()) without guessing what it touched.
#
CREATE TABLE tx_maiseeder_migration_record (
	uid int(11) unsigned NOT NULL auto_increment,
	migration_identifier varchar(191) DEFAULT '' NOT NULL,
	table_name varchar(255) DEFAULT '' NOT NULL,
	record_uid int(11) DEFAULT NULL,
	lookup_criteria text NOT NULL,
	action varchar(20) DEFAULT '' NOT NULL,
	snapshot_before mediumtext,
	crdate int(11) DEFAULT 0 NOT NULL,

	PRIMARY KEY (uid),
	KEY migration_identifier (migration_identifier)
);
