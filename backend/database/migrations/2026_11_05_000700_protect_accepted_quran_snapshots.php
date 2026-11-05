<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * An accepted snapshot is immutable in the database, not only in the application.
 *
 * `docs/quran-data-model.md` invariant I3 says every import is an immutable,
 * version-keyed snapshot. Three holes made that a convention rather than a fact:
 *
 *  1. `validation_status` could be flipped to `passed` with one UPDATE, which
 *     bypassed the V-10 gate entirely;
 *  2. `validation_report` and `content_digest` could be rewritten afterwards,
 *     which made any corruption invisible;
 *  3. rows could be inserted into an accepted snapshot, or re-parented into it
 *     from a `pending` one, because only UPDATE/DELETE on the *old* parent was
 *     checked.
 *
 * This migration closes all three at the storage layer, so `psql` is refused too.
 *
 * What stays allowed:
 *
 *  - the importer writes children while the version is `pending`, then flips the
 *    version to `passed`/`incomplete`/`failed` — a one-way transition;
 *  - activation flips `is_active` and touches `updated_at`;
 *  - maintenance may set `archived_at` (added later) and edit `notes`.
 *
 * PostgreSQL and SQLite are supported; any other driver is refused explicitly
 * rather than being handed SQLite syntax.
 */
return new class extends Migration
{
    private const CHILD_TABLES = ['quran_surahs', 'quran_pages', 'quran_ayahs'];

    /** Statuses whose metadata is frozen: a verdict, once given, cannot be rewritten. */
    private const SETTLED = "('passed', 'incomplete', 'failed')";

    /**
     * Statuses that cannot be deleted. `failed` is deliberately excluded: it has
     * no children and nothing depends on it, and retiring one is how an operator
     * cleans up a bad file (see quran:forget).
     */
    private const KEPT = "('passed', 'incomplete')";

    private const MESSAGE = 'an accepted quran snapshot is immutable';

    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            $this->upPostgres();

            return;
        }

        if ($driver === 'sqlite') {
            $this->upSqlite();

            return;
        }

        throw new RuntimeException(
            "Quran snapshot protection supports PostgreSQL and SQLite only; [{$driver}] is not supported.",
        );
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            foreach (self::CHILD_TABLES as $table) {
                DB::statement("DROP TRIGGER IF EXISTS {$table}_immutable ON {$table}");
            }

            DB::statement('DROP TRIGGER IF EXISTS quran_dataset_versions_immutable ON quran_dataset_versions');
            DB::statement('DROP FUNCTION IF EXISTS quran_snapshot_child_immutable() CASCADE');
            DB::statement('DROP FUNCTION IF EXISTS quran_dataset_version_immutable() CASCADE');

            return;
        }

        if ($driver !== 'sqlite') {
            throw new RuntimeException(
                "Quran snapshot protection supports PostgreSQL and SQLite only; [{$driver}] is not supported.",
            );
        }

        foreach (self::CHILD_TABLES as $table) {
            foreach (['insert', 'update', 'delete'] as $event) {
                DB::statement("DROP TRIGGER IF EXISTS {$table}_immutable_{$event}");
            }
        }

        DB::statement('DROP TRIGGER IF EXISTS quran_dataset_versions_immutable_update');
        DB::statement('DROP TRIGGER IF EXISTS quran_dataset_versions_immutable_delete');
    }

    private function upSqlite(): void
    {
        // A version's identity and its verdict are frozen once it is accepted;
        // only is_active, archived_at and notes may still move.
        DB::statement(sprintf(
            "CREATE TRIGGER quran_dataset_versions_immutable_update
             BEFORE UPDATE ON quran_dataset_versions
             FOR EACH ROW
             BEGIN
                 SELECT RAISE(ABORT, '%s') WHERE
                     OLD.validation_status IN %s
                     AND (
                         NEW.validation_status IS NOT OLD.validation_status
                         OR NEW.validation_report IS NOT OLD.validation_report
                         OR NEW.content_digest IS NOT OLD.content_digest
                         OR NEW.checksum_sha256 IS NOT OLD.checksum_sha256
                         OR NEW.edition IS NOT OLD.edition
                         OR NEW.source IS NOT OLD.source
                         OR NEW.source_version IS NOT OLD.source_version
                         OR NEW.source_url IS NOT OLD.source_url
                         OR NEW.source_format IS NOT OLD.source_format
                         OR NEW.downloaded_at IS NOT OLD.downloaded_at
                         OR NEW.imported_at IS NOT OLD.imported_at
                     );
             END",
            self::MESSAGE,
            self::SETTLED,
        ));

        DB::statement(sprintf(
            "CREATE TRIGGER quran_dataset_versions_immutable_delete
             BEFORE DELETE ON quran_dataset_versions
             FOR EACH ROW
             BEGIN
                 SELECT RAISE(ABORT, '%s') WHERE OLD.validation_status IN %s;
             END",
            self::MESSAGE,
            self::KEPT,
        ));

        foreach (self::CHILD_TABLES as $table) {
            DB::statement(sprintf(
                "CREATE TRIGGER %s_immutable_insert
                 BEFORE INSERT ON %s
                 FOR EACH ROW
                 BEGIN
                     SELECT RAISE(ABORT, '%s')
                     WHERE (SELECT validation_status FROM quran_dataset_versions
                            WHERE id = NEW.dataset_version_id) IN %s;
                 END",
                $table,
                $table,
                self::MESSAGE,
                self::KEPT,
            ));

            // Both ends are checked: moving a row out of a pending snapshot and
            // into an accepted one must be refused just like editing it in place.
            DB::statement(sprintf(
                "CREATE TRIGGER %s_immutable_update
                 BEFORE UPDATE ON %s
                 FOR EACH ROW
                 BEGIN
                     SELECT RAISE(ABORT, '%s')
                     WHERE (SELECT validation_status FROM quran_dataset_versions
                            WHERE id = OLD.dataset_version_id) IN %s
                        OR (SELECT validation_status FROM quran_dataset_versions
                            WHERE id = NEW.dataset_version_id) IN %s;
                 END",
                $table,
                $table,
                self::MESSAGE,
                self::KEPT,
                self::KEPT,
            ));

            DB::statement(sprintf(
                "CREATE TRIGGER %s_immutable_delete
                 BEFORE DELETE ON %s
                 FOR EACH ROW
                 BEGIN
                     SELECT RAISE(ABORT, '%s')
                     WHERE (SELECT validation_status FROM quran_dataset_versions
                            WHERE id = OLD.dataset_version_id) IN %s;
                 END",
                $table,
                $table,
                self::MESSAGE,
                self::KEPT,
            ));
        }
    }

    private function upPostgres(): void
    {
        // `validation_report` is json, which has no equality operator; casting to
        // text is what makes the comparison legal.
        DB::statement(sprintf(
            "CREATE OR REPLACE FUNCTION quran_dataset_version_immutable() RETURNS trigger AS $$
             BEGIN
                 IF TG_OP = 'DELETE' THEN
                     IF OLD.validation_status IN %s THEN
                         RAISE EXCEPTION '%s';
                     END IF;

                     RETURN OLD;
                 END IF;

                 IF OLD.validation_status IN %s AND (
                        NEW.validation_status  IS DISTINCT FROM OLD.validation_status
                     OR NEW.validation_report::text IS DISTINCT FROM OLD.validation_report::text
                     OR NEW.content_digest    IS DISTINCT FROM OLD.content_digest
                     OR NEW.checksum_sha256   IS DISTINCT FROM OLD.checksum_sha256
                     OR NEW.edition           IS DISTINCT FROM OLD.edition
                     OR NEW.source            IS DISTINCT FROM OLD.source
                     OR NEW.source_version    IS DISTINCT FROM OLD.source_version
                     OR NEW.source_url        IS DISTINCT FROM OLD.source_url
                     OR NEW.source_format     IS DISTINCT FROM OLD.source_format
                     OR NEW.downloaded_at     IS DISTINCT FROM OLD.downloaded_at
                     OR NEW.imported_at       IS DISTINCT FROM OLD.imported_at
                 ) THEN
                     RAISE EXCEPTION '%s';
                 END IF;

                 RETURN NEW;
             END;
             $$ LANGUAGE plpgsql",
            self::KEPT,
            self::MESSAGE,
            self::SETTLED,
            self::MESSAGE,
        ));

        DB::statement(
            'CREATE TRIGGER quran_dataset_versions_immutable BEFORE UPDATE OR DELETE ON quran_dataset_versions
             FOR EACH ROW EXECUTE FUNCTION quran_dataset_version_immutable()'
        );

        DB::statement(sprintf(
            "CREATE OR REPLACE FUNCTION quran_snapshot_child_immutable() RETURNS trigger AS $$
             BEGIN
                 IF TG_OP IN ('UPDATE', 'DELETE') AND EXISTS (
                     SELECT 1 FROM quran_dataset_versions
                     WHERE id = OLD.dataset_version_id AND validation_status IN %s
                 ) THEN
                     RAISE EXCEPTION '%s';
                 END IF;

                 IF TG_OP IN ('INSERT', 'UPDATE') AND EXISTS (
                     SELECT 1 FROM quran_dataset_versions
                     WHERE id = NEW.dataset_version_id AND validation_status IN %s
                 ) THEN
                     RAISE EXCEPTION '%s';
                 END IF;

                 IF TG_OP = 'DELETE' THEN
                     RETURN OLD;
                 END IF;

                 RETURN NEW;
             END;
             $$ LANGUAGE plpgsql",
            self::KEPT,
            self::MESSAGE,
            self::SETTLED,
            self::MESSAGE,
        ));

        foreach (self::CHILD_TABLES as $table) {
            DB::statement(sprintf(
                'CREATE TRIGGER %s_immutable BEFORE INSERT OR UPDATE OR DELETE ON %s
                 FOR EACH ROW EXECUTE FUNCTION quran_snapshot_child_immutable()',
                $table,
                $table,
            ));
        }
    }
};
