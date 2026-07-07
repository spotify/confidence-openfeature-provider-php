<?php

declare(strict_types=1);

namespace Confidence\OpenFeature;

final readonly class ResolvedFlag
{
    public function __construct(
        public string $flag,
        public ?string $variant,
        public mixed $value,
    ) {}

    public function isEmpty(): bool
    {
        return $this->variant === null || $this->value === null;
    }
}
