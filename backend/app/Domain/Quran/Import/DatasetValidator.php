<?php

namespace App\Domain\Quran\Import;

use Illuminate\Support\Collection;

/**
 * Structural validation of a parsed dataset (docs/quran-validation-plan.md §3).
 *
 * The checks read the payload only: nothing is written, nothing is repaired.
 * A dataset either passes every blocking check or it is not stored.
 *
 * V-10 and V-12 need a checked-in golden sample, which this repository does not
 * have yet (OD-029); they are reported as skipped rather than quietly dropped.
 */
final class DatasetValidator
{
    /**
     * @return array<string, int>
     */
    private function expected(): array
    {
        return [
            'surahs' => (int) config('quran.import.expected.surahs', 114),
            'ayahs' => (int) config('quran.import.expected.ayahs', 6236),
            'pages' => (int) config('quran.import.expected.pages', 604),
        ];
    }

    public function validate(DatasetPayload $payload): ValidationReport
    {
        $surahs = collect($payload->surahs);
        $pages = collect($payload->pages);
        $ayahs = collect($payload->ayahs);

        $surahById = $surahs->keyBy('id');
        $pageByNumber = $pages->keyBy('page_number');
        $ayahsBySurah = $ayahs->groupBy('surah_id');

        return ValidationReport::fromChecks([
            $this->surahsComplete($surahs),
            $this->ayahTotals($surahs, $ayahs, $ayahsBySurah),
            $this->pagesComplete($pages),
            $this->pageOrder($ayahs),
            $this->coordinates($ayahs, $pageByNumber),
            $this->globalAyahIds($ayahs),
            $this->duplicates($ayahs),
            $this->ordering($surahs, $ayahs),
            $this->text($surahs, $ayahs),
            $this->skipped('V-10', 'deterministic sample comparison', 'no golden sample is checked in (OD-029)'),
            $this->referentialIntegrity($ayahs, $surahById, $pageByNumber),
            $this->skipped('V-12', 'golden boundary fixtures', 'no golden fixture is checked in (OD-029)'),
            $this->requiredFields($surahs, $pages, $ayahs),
            $this->editionPageProfile($payload->edition, $pages),
        ]);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $surahs
     * @return array{id: string, label: string, status: string, detail: string}
     */
    private function surahsComplete(Collection $surahs): array
    {
        $expected = $this->expected()['surahs'];
        $ids = $surahs->pluck('id')->map(static fn ($id): int => (int) $id)->sort()->values()->all();
        $missing = array_values(array_diff(range(1, $expected), $ids));

        if (count($ids) !== $expected) {
            return $this->failed('V-01', 'surah count', sprintf(
                'expected %d surahs, found %d (missing: %s)',
                $expected,
                count($ids),
                $missing === [] ? 'none' : implode(', ', array_slice($missing, 0, 10)),
            ));
        }

        if ($missing !== []) {
            return $this->failed('V-01', 'surah count', 'missing surah numbers: '.implode(', ', array_slice($missing, 0, 10)));
        }

        if ($ids !== range(1, $expected)) {
            return $this->failed('V-01', 'surah count', 'surah numbers are not exactly 1…'.$expected);
        }

        return $this->passed('V-01', 'surah count', $expected.' surahs, numbered 1…'.$expected);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $surahs
     * @param  Collection<int, array<string, mixed>>  $ayahs
     * @param  Collection<int, Collection<int, array<string, mixed>>>  $ayahsBySurah
     * @return array{id: string, label: string, status: string, detail: string}
     */
    private function ayahTotals(Collection $surahs, Collection $ayahs, Collection $ayahsBySurah): array
    {
        $expected = $this->expected()['ayahs'];

        if ($ayahs->count() !== $expected) {
            return $this->failed('V-02', 'ayah counts', sprintf(
                'expected %d ayahs, found %d',
                $expected,
                $ayahs->count(),
            ));
        }

        $declared = 0;
        $problems = [];

        foreach ($surahs as $surah) {
            $id = (int) $surah['id'];
            $actual = $ayahsBySurah->get($id)?->count() ?? 0;
            $declared += (int) $surah['ayah_count'];

            if ($actual !== (int) $surah['ayah_count']) {
                $problems[] = sprintf('surah %d: declared %d, found %d', $id, (int) $surah['ayah_count'], $actual);
            }
        }

        if ($problems !== []) {
            return $this->failed('V-02', 'ayah counts', implode('; ', array_slice($problems, 0, 5)));
        }

        if ($declared !== $expected) {
            return $this->failed('V-02', 'ayah counts', sprintf(
                'declared ayah counts sum to %d, expected %d',
                $declared,
                $expected,
            ));
        }

        return $this->passed('V-02', 'ayah counts', $expected.' ayahs, consistent with every surah count');
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $pages
     * @return array{id: string, label: string, status: string, detail: string}
     */
    private function pagesComplete(Collection $pages): array
    {
        $expected = $this->expected()['pages'];
        $numbers = $pages->pluck('page_number')->map(static fn ($n): int => (int) $n)->sort()->values()->all();

        if (count($numbers) !== $expected) {
            return $this->failed('V-03', 'pagination', sprintf(
                'expected %d pages, found %d',
                $expected,
                count($numbers),
            ));
        }

        if ($numbers !== range(1, $expected)) {
            $missing = array_values(array_diff(range(1, $expected), $numbers));

            return $this->failed('V-03', 'pagination', 'page numbers are not 1…'.$expected
                .($missing === [] ? '' : ' (missing: '.implode(', ', array_slice($missing, 0, 10)).')'));
        }

        return $this->passed('V-03', 'pagination', $expected.' pages, numbered 1…'.$expected);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $ayahs
     * @return array{id: string, label: string, status: string, detail: string}
     */
    private function pageOrder(Collection $ayahs): array
    {
        $expected = $this->expected()['pages'];
        $previousPage = 0;
        $previousId = 0;

        foreach ($ayahs->sortBy('id') as $ayah) {
            $page = (int) $ayah['page_start'];
            $id = (int) $ayah['id'];

            if ($page < 1 || $page > $expected) {
                return $this->failed('V-04', 'page ranges', sprintf(
                    'ayah %d starts on page %d, outside 1…%d',
                    $id,
                    $page,
                    $expected,
                ));
            }

            if ($page < $previousPage) {
                return $this->failed('V-04', 'page ranges', sprintf(
                    'ayah %d starts on page %d after ayah %d on page %d',
                    $id,
                    $page,
                    $previousId,
                    $previousPage,
                ));
            }

            $previousPage = $page;
            $previousId = $id;
        }

        return $this->passed('V-04', 'page ranges', 'pages are non-decreasing in mushaf order');
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $ayahs
     * @param  Collection<int, array<string, mixed>>  $pageByNumber
     * @return array{id: string, label: string, status: string, detail: string}
     */
    private function coordinates(Collection $ayahs, Collection $pageByNumber): array
    {
        foreach ($ayahs as $ayah) {
            $id = (int) $ayah['id'];
            $pageStart = (int) $ayah['page_start'];
            $lineStart = (int) $ayah['line_start'];
            $pageEnd = (int) $ayah['page_end'];
            $lineEnd = (int) $ayah['line_end'];

            $startHeight = $pageByNumber->get($pageStart)['lines_count'] ?? null;
            $endHeight = $pageByNumber->get($pageEnd)['lines_count'] ?? null;

            if ($startHeight === null || $endHeight === null) {
                return $this->failed('V-05', 'coordinates', sprintf(
                    'ayah %d references a page that is not in the dataset (%d…%d)',
                    $id,
                    $pageStart,
                    $pageEnd,
                ));
            }

            if ($pageEnd < $pageStart) {
                return $this->failed('V-05', 'coordinates', sprintf(
                    'ayah %d ends on page %d before it starts on page %d',
                    $id,
                    $pageEnd,
                    $pageStart,
                ));
            }

            if ($lineStart < 1 || $lineStart > (int) $startHeight) {
                return $this->failed('V-05', 'coordinates', sprintf(
                    'ayah %d starts on line %d, outside page %d (1…%d)',
                    $id,
                    $lineStart,
                    $pageStart,
                    (int) $startHeight,
                ));
            }

            if ($lineEnd < 1 || $lineEnd > (int) $endHeight) {
                return $this->failed('V-05', 'coordinates', sprintf(
                    'ayah %d ends on line %d, outside page %d (1…%d)',
                    $id,
                    $lineEnd,
                    $pageEnd,
                    (int) $endHeight,
                ));
            }

            if ($pageEnd === $pageStart && $lineEnd < $lineStart) {
                return $this->failed('V-05', 'coordinates', sprintf(
                    'ayah %d ends on line %d before it starts on line %d',
                    $id,
                    $lineEnd,
                    $lineStart,
                ));
            }
        }

        return $this->passed('V-05', 'coordinates', 'every ayah sits inside its page height');
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $ayahs
     * @return array{id: string, label: string, status: string, detail: string}
     */
    private function globalAyahIds(Collection $ayahs): array
    {
        $expected = $this->expected()['ayahs'];
        $ids = $ayahs->pluck('id')->map(static fn ($id): int => (int) $id)->sort()->values()->all();

        if ($ids !== range(1, $expected)) {
            $missing = array_values(array_diff(range(1, $expected), $ids));

            return $this->failed('V-06', 'no missing ayahs', 'global ids are not 1…'.$expected
                .($missing === [] ? '' : ' (missing: '.implode(', ', array_slice($missing, 0, 10)).')'));
        }

        return $this->passed('V-06', 'no missing ayahs', 'global ids form 1…'.$expected);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $ayahs
     * @return array{id: string, label: string, status: string, detail: string}
     */
    private function duplicates(Collection $ayahs): array
    {
        $byId = $ayahs->countBy(static fn (array $ayah): int => (int) $ayah['id'])->filter(static fn (int $n): bool => $n > 1);
        $byPosition = $ayahs
            ->countBy(static fn (array $ayah): string => $ayah['surah_id'].':'.$ayah['number_in_surah'])
            ->filter(static fn (int $n): bool => $n > 1);

        if ($byId->isNotEmpty()) {
            return $this->failed('V-07', 'duplicates', 'duplicate ayah ids: '.implode(', ', $byId->keys()->take(5)->all()));
        }

        if ($byPosition->isNotEmpty()) {
            return $this->failed('V-07', 'duplicates', 'duplicate surah:ayah pairs: '.implode(', ', $byPosition->keys()->take(5)->all()));
        }

        return $this->passed('V-07', 'duplicates', 'no duplicate id and no duplicate surah:ayah pair');
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $surahs
     * @param  Collection<int, array<string, mixed>>  $ayahs
     * @return array{id: string, label: string, status: string, detail: string}
     */
    private function ordering(Collection $surahs, Collection $ayahs): array
    {
        $previousKey = [0, 0];
        $previousPage = 0;

        foreach ($ayahs->sortBy('id') as $ayah) {
            $key = [(int) $ayah['surah_id'], (int) $ayah['number_in_surah']];

            if ($key[0] < $previousKey[0] || ($key[0] === $previousKey[0] && $key[1] <= $previousKey[1])) {
                return $this->failed('V-08', 'ordering', sprintf(
                    'ayah %d (%d:%d) is out of mushaf order after %d:%d',
                    (int) $ayah['id'],
                    $key[0],
                    $key[1],
                    $previousKey[0],
                    $previousKey[1],
                ));
            }

            $previousKey = $key;
        }

        foreach ($surahs->sortBy('id') as $surah) {
            $pageStart = (int) $surah['page_start'];

            if ($pageStart < $previousPage) {
                return $this->failed('V-08', 'ordering', sprintf(
                    'surah %d starts on page %d, before the previous surah on page %d',
                    (int) $surah['id'],
                    $pageStart,
                    $previousPage,
                ));
            }

            $previousPage = $pageStart;
        }

        return $this->passed('V-08', 'ordering', 'ayah order and surah start pages are monotonic');
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $surahs
     * @param  Collection<int, array<string, mixed>>  $ayahs
     * @return array{id: string, label: string, status: string, detail: string}
     */
    private function text(Collection $surahs, Collection $ayahs): array
    {
        foreach ($ayahs as $ayah) {
            $text = trim((string) ($ayah['text_uthmani'] ?? ''));

            if ($text === '') {
                return $this->failed('V-09', 'text', sprintf('ayah %d has empty text', (int) $ayah['id']));
            }

            if (preg_match('/[A-Za-z]/', $text) === 1) {
                return $this->failed('V-09', 'text', sprintf('ayah %d contains Latin letters', (int) $ayah['id']));
            }
        }

        foreach ($surahs as $surah) {
            if (trim((string) ($surah['name_ar'] ?? '')) === '') {
                return $this->failed('V-09', 'text', sprintf('surah %d has an empty Arabic name', (int) $surah['id']));
            }
        }

        return $this->passed('V-09', 'text', 'every ayah has Uthmani text with no Latin letters');
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $ayahs
     * @param  Collection<int, array<string, mixed>>  $surahById
     * @param  Collection<int, array<string, mixed>>  $pageByNumber
     * @return array{id: string, label: string, status: string, detail: string}
     */
    private function referentialIntegrity(Collection $ayahs, Collection $surahById, Collection $pageByNumber): array
    {
        foreach ($ayahs as $ayah) {
            $surahId = (int) $ayah['surah_id'];

            if (! $surahById->has($surahId)) {
                return $this->failed('V-13', 'referential integrity', sprintf(
                    'ayah %d points at surah %d, which is not in the dataset',
                    (int) $ayah['id'],
                    $surahId,
                ));
            }

            $number = (int) $ayah['number_in_surah'];
            $count = (int) $surahById->get($surahId)['ayah_count'];

            if ($number < 1 || $number > $count) {
                return $this->failed('V-13', 'referential integrity', sprintf(
                    'ayah %d is number %d in surah %d, which declares %d ayahs',
                    (int) $ayah['id'],
                    $number,
                    $surahId,
                    $count,
                ));
            }

            if (! $pageByNumber->has((int) $ayah['page_end'])) {
                return $this->failed('V-13', 'referential integrity', sprintf(
                    'ayah %d ends on page %d, which is not in the dataset',
                    (int) $ayah['id'],
                    (int) $ayah['page_end'],
                ));
            }
        }

        return $this->passed('V-13', 'referential integrity', 'no orphan ayah');
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $surahs
     * @param  Collection<int, array<string, mixed>>  $pages
     * @param  Collection<int, array<string, mixed>>  $ayahs
     * @return array{id: string, label: string, status: string, detail: string}
     */
    private function requiredFields(Collection $surahs, Collection $pages, Collection $ayahs): array
    {
        foreach ($surahs as $surah) {
            foreach (['id', 'name_ar', 'name_en', 'ayah_count', 'page_start', 'page_end'] as $field) {
                if (! isset($surah[$field]) || $surah[$field] === '') {
                    return $this->failed('V-14', 'required fields', sprintf('surah row is missing "%s"', $field));
                }
            }
        }

        foreach ($pages as $page) {
            foreach (['page_number', 'lines_count', 'line_offset'] as $field) {
                if (! isset($page[$field])) {
                    return $this->failed('V-14', 'required fields', sprintf('page row is missing "%s"', $field));
                }
            }

            if ((int) $page['lines_count'] < 1) {
                return $this->failed('V-14', 'required fields', sprintf(
                    'page %d declares %d lines',
                    (int) $page['page_number'],
                    (int) $page['lines_count'],
                ));
            }
        }

        foreach ($ayahs as $ayah) {
            foreach (['id', 'surah_id', 'number_in_surah', 'text_uthmani', 'page_start', 'line_start', 'page_end', 'line_end'] as $field) {
                if (! isset($ayah[$field]) || $ayah[$field] === '') {
                    return $this->failed('V-14', 'required fields', sprintf('ayah row is missing "%s"', $field));
                }
            }
        }

        return $this->passed('V-14', 'required fields', 'no required field is empty');
    }

    /**
     * Page heights are validated against the edition's documented profile — the
     * dataset must still declare them; they are never derived from ayah coverage.
     *
     * @param  Collection<int, array<string, mixed>>  $pages
     * @return array{id: string, label: string, status: string, detail: string}
     */
    private function editionPageProfile(string $edition, Collection $pages): array
    {
        $profiles = (array) config('quran.import.page_height_profiles', []);
        $profile = $profiles[$edition] ?? null;

        if ($profile === null) {
            return $this->skipped('V-15', 'edition page heights', 'no documented height profile for edition "'.$edition.'"');
        }

        $shortPages = array_map('intval', (array) $profile['short_pages']);
        $shortLines = (int) $profile['short_lines'];
        $lines = (int) $profile['lines'];
        $problems = [];

        foreach ($pages as $page) {
            $number = (int) $page['page_number'];
            $expected = in_array($number, $shortPages, true) ? $shortLines : $lines;

            if ((int) $page['lines_count'] !== $expected) {
                $problems[] = sprintf('page %d declares %d lines, profile expects %d', $number, (int) $page['lines_count'], $expected);
            }
        }

        if ($problems !== []) {
            return $this->failed('V-15', 'edition page heights', implode('; ', array_slice($problems, 0, 5)));
        }

        return $this->passed('V-15', 'edition page heights', sprintf(
            'pages %s have %d lines, the rest have %d',
            implode(', ', $shortPages),
            $shortLines,
            $lines,
        ));
    }

    /**
     * @return array{id: string, label: string, status: string, detail: string}
     */
    private function passed(string $id, string $label, string $detail): array
    {
        return ['id' => $id, 'label' => $label, 'status' => ValidationReport::PASSED, 'detail' => $detail];
    }

    /**
     * @return array{id: string, label: string, status: string, detail: string}
     */
    private function failed(string $id, string $label, string $detail): array
    {
        return ['id' => $id, 'label' => $label, 'status' => ValidationReport::FAILED, 'detail' => $detail];
    }

    /**
     * @return array{id: string, label: string, status: string, detail: string}
     */
    private function skipped(string $id, string $label, string $detail): array
    {
        return ['id' => $id, 'label' => $label, 'status' => ValidationReport::SKIPPED, 'detail' => $detail];
    }
}
