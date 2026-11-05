<?php

namespace Tests\Unit\Quran;

use App\Domain\Quran\Import\ContentDigest;
use App\Domain\Quran\Import\DatasetPayload;
use PHPUnit\Framework\TestCase;

/**
 * The digest must change for anything that changes what a reader sees or what a
 * measurement uses — and must not change for anything else.
 */
class ContentDigestTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'edition' => 'synthetic_regression',
            'source_version' => 'test-v1',
            'surahs' => [
                ['id' => 1, 'name_ar' => 'سورة أولى', 'name_en' => 'One', 'ayah_count' => 2, 'page_start' => 1, 'page_end' => 1],
                ['id' => 2, 'name_ar' => 'سورة ثانية', 'name_en' => 'Two', 'ayah_count' => 1, 'page_start' => 1, 'page_end' => 2],
            ],
            'pages' => [
                ['page_number' => 1, 'lines_count' => 8, 'line_offset' => 0, 'juz_number' => 1],
                ['page_number' => 2, 'lines_count' => 15, 'line_offset' => 8, 'juz_number' => 1],
            ],
            'ayahs' => [
                ['id' => 1, 'surah_id' => 1, 'number_in_surah' => 1, 'text_uthmani' => 'نص اصطناعي ١', 'page_start' => 1, 'line_start' => 1, 'page_end' => 1, 'line_end' => 1, 'juz_number' => 1, 'is_sajda' => false],
                ['id' => 2, 'surah_id' => 1, 'number_in_surah' => 2, 'text_uthmani' => 'نص اصطناعي ٢', 'page_start' => 1, 'line_start' => 2, 'page_end' => 1, 'line_end' => 3, 'juz_number' => 1, 'is_sajda' => false],
                ['id' => 3, 'surah_id' => 2, 'number_in_surah' => 1, 'text_uthmani' => 'نص اصطناعي ٣', 'page_start' => 1, 'line_start' => 8, 'page_end' => 2, 'line_end' => 2, 'juz_number' => 1, 'is_sajda' => false],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function digest(array $data): string
    {
        return ContentDigest::of(new DatasetPayload(
            edition: (string) $data['edition'],
            sourceVersion: (string) $data['source_version'],
            surahs: $data['surahs'],
            pages: $data['pages'],
            ayahs: $data['ayahs'],
        ));
    }

    public function test_it_is_deterministic(): void
    {
        $this->assertSame($this->digest($this->payload()), $this->digest($this->payload()));
    }

    public function test_row_order_does_not_change_it(): void
    {
        $reordered = $this->payload();
        $reordered['ayahs'] = array_reverse($reordered['ayahs']);
        $reordered['surahs'] = array_reverse($reordered['surahs']);
        $reordered['pages'] = array_reverse($reordered['pages']);

        $this->assertSame($this->digest($this->payload()), $this->digest($reordered));
    }

    public function test_changing_ayah_text_changes_it(): void
    {
        $data = $this->payload();
        $data['ayahs'][0]['text_uthmani'] = 'نص مختلف';

        $this->assertNotSame($this->digest($this->payload()), $this->digest($data));
    }

    public function test_changing_an_ayah_page_changes_it(): void
    {
        $data = $this->payload();
        $data['ayahs'][0]['page_start'] = 2;
        $data['ayahs'][0]['page_end'] = 2;

        $this->assertNotSame($this->digest($this->payload()), $this->digest($data));
    }

    public function test_changing_an_ayah_line_changes_it(): void
    {
        $data = $this->payload();
        $data['ayahs'][0]['line_start'] = 3;

        $this->assertNotSame($this->digest($this->payload()), $this->digest($data));
    }

    public function test_changing_surah_data_changes_it(): void
    {
        $data = $this->payload();
        $data['surahs'][0]['name_ar'] = 'سورة معدّلة';

        $this->assertNotSame($this->digest($this->payload()), $this->digest($data));
    }

    public function test_changing_a_page_height_changes_it(): void
    {
        $data = $this->payload();
        $data['pages'][1]['lines_count'] = 14;

        $this->assertNotSame($this->digest($this->payload()), $this->digest($data));
    }

    public function test_removing_a_row_changes_it(): void
    {
        $data = $this->payload();
        array_pop($data['ayahs']);

        $this->assertNotSame($this->digest($this->payload()), $this->digest($data));
    }

    public function test_the_canonical_form_is_versioned(): void
    {
        $canonical = ContentDigest::canonical(new DatasetPayload(
            edition: 'e',
            sourceVersion: 'v',
            surahs: $this->payload()['surahs'],
            pages: $this->payload()['pages'],
            ayahs: $this->payload()['ayahs'],
        ));

        $this->assertStringStartsWith(ContentDigest::VERSION."\n", $canonical);
    }
}
