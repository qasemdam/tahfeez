<?php

namespace Tests\Unit\Quran;

use App\Domain\Quran\Import\StorageFailure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * A storage failure must not put the statement, its bindings or the ayah text
 * into anything the operator or the log can read.
 */
class StorageFailureTest extends TestCase
{
    private function exception(): QueryException
    {
        return new QueryException(
            'sqlite',
            'insert into "quran_ayahs" ("text_uthmani") values (?)',
            ['نص الآية السري'],
            new \RuntimeException('constraint failed'),
        );
    }

    public function test_the_raw_exception_does_carry_the_text(): void
    {
        // Establishes why the wrapper exists: the driver message embeds both the
        // statement and its bound values.
        $this->assertStringContainsString('نص الآية السري', $this->exception()->getMessage());
    }

    public function test_the_reported_message_carries_no_statement_or_values(): void
    {
        $message = StorageFailure::describe($this->exception());

        $this->assertStringNotContainsString('نص الآية السري', $message);
        $this->assertStringNotContainsString('insert into', $message);
        $this->assertStringNotContainsString('quran_ayahs', $message);
        $this->assertStringContainsString('reference', $message);
    }

    public function test_the_log_entry_carries_only_the_class_and_reference(): void
    {
        Log::spy();

        StorageFailure::describe($this->exception());

        Log::shouldHaveReceived('error')
            ->once()
            ->withArgs(function (string $event, array $context): bool {
                $payload = json_encode($context, JSON_UNESCAPED_UNICODE);

                return $event === 'quran.import.store_failed'
                    && ! str_contains((string) $payload, 'نص الآية السري')
                    && ! str_contains((string) $payload, 'insert into')
                    && isset($context['reference'], $context['exception']);
            });
    }
}
