<?php

namespace App\Domain\Quran\Import;

/**
 * A deterministic fingerprint of a dataset's content (S6.3).
 *
 * It answers a different question from `checksum_sha256`:
 *
 *  - the checksum says *which file* was imported;
 *  - the digest says *what is stored now*.
 *
 * A row can be edited without touching the source file, so the checksum keeps
 * matching while the data drifts. Recomputing this digest and comparing it with
 * the stored one is what detects that.
 *
 * Canonical form — `tahfeez-quran-digest-v1`, one record per line, `\n` between
 * lines, SHA-256 over the UTF-8 bytes:
 *
 *     tahfeez-quran-digest-v1
 *     S["surah",1,"الفاتحة","Al-Fatihah",7,1,1]
 *     P["page",1,8,0,1,null,null]
 *     A["ayah",1,1,1,"بِسْمِ…",1,1,1,1,1,false]
 *
 * Rules that make it stable across drivers and runs:
 *
 *  - rows are sorted by their primary key, never by insertion order;
 *  - every value is cast before encoding (`int`, `string`, `bool`, `null`), so
 *    a driver returning "1" instead of 1 cannot change the hash;
 *  - JSON escaping is disabled for unicode and slashes, and the field order is
 *    fixed by the code, not by the database;
 *  - the dataset version id is **not** part of the payload: the digest
 *    fingerprints content, so two snapshots with identical content hash equal.
 */
final class ContentDigest
{
    public const VERSION = 'tahfeez-quran-digest-v1';

    public static function of(DatasetPayload $payload): string
    {
        return hash('sha256', self::canonical($payload));
    }

    public static function canonical(DatasetPayload $payload): string
    {
        $lines = [self::VERSION];

        $surahs = $payload->surahs;
        usort($surahs, static fn (array $a, array $b): int => (int) $a['id'] <=> (int) $b['id']);

        foreach ($surahs as $surah) {
            $lines[] = 'S'.self::json([
                'surah',
                (int) $surah['id'],
                (string) $surah['name_ar'],
                (string) ($surah['name_en'] ?? ''),
                (int) $surah['ayah_count'],
                (int) $surah['page_start'],
                (int) $surah['page_end'],
            ]);
        }

        $pages = $payload->pages;
        usort($pages, static fn (array $a, array $b): int => (int) $a['page_number'] <=> (int) $b['page_number']);

        foreach ($pages as $page) {
            $lines[] = 'P'.self::json([
                'page',
                (int) $page['page_number'],
                (int) $page['lines_count'],
                (int) $page['line_offset'],
                self::nullableInt($page['juz_number'] ?? null),
                self::nullableInt($page['hizb_number'] ?? null),
                self::nullableInt($page['rub_number'] ?? null),
            ]);
        }

        $ayahs = $payload->ayahs;
        usort($ayahs, static fn (array $a, array $b): int => (int) $a['id'] <=> (int) $b['id']);

        foreach ($ayahs as $ayah) {
            $lines[] = 'A'.self::json([
                'ayah',
                (int) $ayah['id'],
                (int) $ayah['surah_id'],
                (int) $ayah['number_in_surah'],
                (string) $ayah['text_uthmani'],
                (int) $ayah['page_start'],
                (int) $ayah['line_start'],
                (int) $ayah['page_end'],
                (int) $ayah['line_end'],
                self::nullableInt($ayah['juz_number'] ?? null),
                (bool) ($ayah['is_sajda'] ?? false),
            ]);
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<int, mixed>  $fields
     */
    private static function json(array $fields): string
    {
        return json_encode($fields, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private static function nullableInt(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }
}
