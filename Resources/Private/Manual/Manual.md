---
version: "1.0"
date: 2026-11-06
---

# Handbuch: Content-Migrationen

Migrationen füllen oder ändern Datensätze, nachdem die Tabellen existieren. Das Datenbankschema selbst bleibt bei den Extensions und beim Schema-Update.

## Ausführen

Auf der Kommandozeile: `vendor/bin/typo3 mai-seeder:migrate` und `mai-seeder:migrate:status`.

Administratoren finden dasselbe unter **System → Content Migrations**, inklusive Rücknahme.

## Neue Migration

`mai-seeder:make:migration` legt eine Klasse an. Jede Extension mit Klassen unter `Classes/Migrations/` wird automatisch gefunden. Einträge werden über einen fachlichen Schlüssel gesucht, nicht über die uid, damit derselbe Lauf auf jeder Umgebung funktioniert.
