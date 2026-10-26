<?php

namespace Tests\Unit\Support\Voyager;

use App\Support\Voyager\BreadMediaRemoval;
use Exception;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BreadMediaRemovalTest extends TestCase
{
    public function test_serialized_file_field_after_removing_last_file_is_empty_json_array(): void
    {
        $this->assertSame('[]', BreadMediaRemoval::serializedFileFieldAfterRemoval([]));
    }

    public function test_serialized_file_field_reindexes_remaining_files(): void
    {
        $payload = [
            2 => ['download_link' => 'path/b.pdf', 'original_name' => 'b.pdf'],
        ];

        $this->assertSame(
            json_encode([['download_link' => 'path/b.pdf', 'original_name' => 'b.pdf']]),
            BreadMediaRemoval::serializedFileFieldAfterRemoval($payload)
        );
    }

    #[DataProvider('httpStatusCodeProvider')]
    public function test_http_status_code_from_exception(int $exceptionCode, int $expectedHttpCode): void
    {
        $exception = new Exception('test', $exceptionCode);

        $this->assertSame($expectedHttpCode, BreadMediaRemoval::httpStatusCodeFromException($exception));
    }

    /**
     * @return array<string, array{0: int, 1: int}>
     */
    public static function httpStatusCodeProvider(): array
    {
        return [
            'application error' => [400, 400],
            'not found' => [404, 404],
            'sql state code is not valid http' => [23000, 500],
            'zero defaults to 500' => [0, 500],
        ];
    }
}
