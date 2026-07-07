<?php

declare(strict_types=1);

namespace Confidence\OpenFeature;

interface ApiClientInterface
{
    /**
     * @param array<string, mixed> $context
     */
    public function resolveOne(string $flag, array $context = [], bool $apply = true): ?ResolvedFlag;
}
