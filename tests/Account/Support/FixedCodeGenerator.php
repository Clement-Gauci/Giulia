<?php
namespace App\Tests\Account\Support;

use App\Account\Domain\LoginCodeGenerator;

final readonly class FixedCodeGenerator implements LoginCodeGenerator
{
    public function __construct(private string $code) {}

    public function generate(): string
    {
        return $this->code;
    }
}
