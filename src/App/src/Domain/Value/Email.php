<?php

declare(strict_types=1);

namespace App\Domain\Value;

use InvalidArgumentException;

use function filter_var;
use function strtolower;
use function trim;

use const FILTER_VALIDATE_EMAIL;

final readonly class Email
{
    private function __construct(public string $value)
    {
    }

    public static function fromString(string $value): self
    {
        $value = strtolower(trim($value));

        if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException("Invalid email address: \"{$value}\"");
        }

        return new self($value);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
