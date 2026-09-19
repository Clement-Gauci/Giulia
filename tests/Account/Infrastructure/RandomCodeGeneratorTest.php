<?php
namespace App\Tests\Account\Infrastructure;

use App\Account\Infrastructure\RandomCodeGenerator;
use PHPUnit\Framework\TestCase;

final class RandomCodeGeneratorTest extends TestCase
{
    public function test_it_always_generates_exactly_six_digits(): void
    {
        $generator = new RandomCodeGenerator();

        // Assez de tirages pour attraper les codes à zéros de tête, qu'un
        // formatage naïf raccourcirait à cinq chiffres ou moins.
        for ($i = 0; $i < 500; $i++) {
            self::assertMatchesRegularExpression('/^\d{6}$/', $generator->generate());
        }
    }

    public function test_two_codes_in_a_row_are_not_the_same(): void
    {
        $generator = new RandomCodeGenerator();

        $codes = [];
        for ($i = 0; $i < 20; $i++) {
            $codes[] = $generator->generate();
        }

        self::assertGreaterThan(15, count(array_unique($codes)));
    }
}
