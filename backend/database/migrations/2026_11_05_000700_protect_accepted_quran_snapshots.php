<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * An accepted snapshot is immutable in the database, not only in the application
 * (S6.3).
 *
 * `docs/quran-data-model.md` invariant I3 says every import is an immutable,
 * version-keyed snapshot. Until now that was a convention: nothing stopped an
 * UPDATE or a DELETE on `quran_surahs`, `quran_pages` or `quran_ayahs`, so a
 * snapshot could drift away from the report that cleared it — and the provider
 * would keep serving it because `validation_status` still said `passed`.
 *
 * These triggers close that hole at the storage layer, for any client including
 * psql. They fire only when the owning snapshot is `passed` or `incomplete`:
 *
 *  - the importer inserts while the version is still `pending`, so writing is
 *    unaffected;
 *  - a `failed` snapshot keeps no children, and a `pending` one must stay
 *    editable while the transaction runs;
 *  - deleting an accepted version is refused too — otherwise the FK cascade
 *    would remove its children and bypass the child triggers.
 *
 * Activation updates `quran_dataset_versions.is_active`, which is an UPDATE on
 * the versions table and is deliberately still allowed; only DELETE is blocked
 * there.
 *
 * PostgreSQL and SQLite both support row triggers, with different syntax; MySQL
 * is not supported by this schema.
 */
return new class extends Migration
{
    private const CHILD_TABLES = ['quran_surahs', 'quran_pages', 'quran_ayahs'];

    private const MESSAGE = 'quran dataset rows are immutable once the snapshot is accepted';

    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $this->upPostgres();

            return;
        }

        $this->upSqlite();
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            foreach (self::CHILD_TABLES as $table) {
                DB::statement("DROP TRIGGER IF EXISTS {$table}_immutable ON {$table}");
            }

            DB::statement('DROP TRIGGER IF EXISTS quran_dataset_versions_immutable ON quran_dataset_versions');
            DB::statement('DROP FUNCTION IF EXISTS quran_snapshot_immutable() CASCADE');
            DB::statement('DROP FUNCTION IF EXISTS quran_dataset_version_immutable() CASCADE');

            return;
        }

        foreach (self::CHILD_TABLES as $table) {
            DB::statement("DROP TRIGGER IF EXISTS {$table}_immutable_update");
            DB::statement("DROP TRIGGER IF EXISTS {$table}_immutable_delete");
        }

        DB::statement('DROP TRIGGER IF EXISTS quran_dataset_versions_immutable_delete');
    }

    private function upSqlite(): void
    {
        foreach (self::CHILD_TABLES as $table) {
            foreach (['UPDATE', 'DELETE'] as $event) {
                DB::statement(sprintf(
                    "CREATE TRIGGER %s_immutable_%s
                     BEFORE %s ON %s
                     FOR EACH ROW
                     BEGIN
                         SELECT RAISE(ABORT, '%s')
                         WHERE (SELECT validation_status FROM quran_dataset_versions
                                WHERE id = OLD.dataset_version_id) IN ('passed', 'incomplete');
                     END",
                    $table,
                    strtolower($event),
                    $event,
                    $table,
                    self::MESSAGE,
                ));
            }
        }

        DB::statement(sprintf(
            "CREATE TRIGGER quran_dataset_versions_immutable_delete
             BEFORE DELETE ON quran_dataset_versions
             FOR EACH ROW
             BEGIN
                 SELECT RAISE(ABORT, '%s') WHERE OLD.validation_status IN ('passed', 'incomplete');
             END",
            self::MESSAGE,
        ));
    }

    private function upPostgres(): void
    {
        DB::statement(sprintf(
            "CREATE OR REPLACE FUNCTION quran_snapshot_immutable() RETURNS trigger AS $$
             BEGIN
                 IF EXISTS (
                     SELECT 1 FROM quran_dataset_versions
                     WHERE id = OLD.dataset_version_id
                       AND validation_status IN ('passed', 'incomplete')
                 ) THEN
                     RAISE EXCEPTION '%s';
                 END IF;

                 IF TG_OP = 'DELETE' THEN
                     RETURN OLD;
                 END IF;

                 RETURN NEW;
             END;
             $$ LANGUAGE plpgsql",
            self::MESSAGE,
        ));

        foreach (self::CHILD_TABLES as $table) {
            DB::statement(sprintf(
                'CREATE TRIGGER %s_immutable BEFORE UPDATE OR DELETE ON %s
                 FOR EACH ROW EXECUTE FUNCTION quran_snapshot_immutable()',
                $table,
                $table,
            ));
        }

        DB::statement(sprintf(
            "CREATE OR REPLACE FUNCTION quran_dataset_version_immutable() RETURNS trigger AS $$
             BEGIN
                 IF OLD.validation_status IN ('passed', 'incomplete') THEN
                     RAISE EXCEPTION '%s';
                 END IF;

                 RETURN OLD;
             END;
             $$ LANGUAGE plpgsql",
            self::MESSAGE,
        ));

        DB::statement(
            'CREATE TRIGGER quran_dataset_versions_immutable BEFORE DELETE ON quran_dataset_versions
             FOR EACH ROW EXECUTE FUNCTION quran_dataset_version_immutable()'
        );
    }
};
