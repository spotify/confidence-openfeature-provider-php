<?php

declare(strict_types=1);

namespace Confidence\OpenFeature;

use OpenFeature\implementation\provider\AbstractProvider;
use OpenFeature\implementation\provider\ResolutionDetailsBuilder;
use OpenFeature\implementation\provider\ResolutionError;
use OpenFeature\interfaces\flags\EvaluationContext;
use OpenFeature\interfaces\provider\ErrorCode;
use OpenFeature\interfaces\provider\ResolutionDetails as ResolutionDetailsInterface;

class ConfidenceProvider extends AbstractProvider
{
    protected static string $NAME = 'Confidence';

    public function __construct(
        private readonly ApiClientInterface $apiClient,
        private readonly bool $applyOnResolve = true,
    ) {}

    public function resolveBooleanValue(string $flagKey, bool $defaultValue, ?EvaluationContext $context = null): ResolutionDetailsInterface
    {
        return $this->evaluate($flagKey, $defaultValue, $context, fn($v) => $v === true || $v === false);
    }

    public function resolveStringValue(string $flagKey, string $defaultValue, ?EvaluationContext $context = null): ResolutionDetailsInterface
    {
        return $this->evaluate($flagKey, $defaultValue, $context, fn($v) => is_string($v));
    }

    public function resolveIntegerValue(string $flagKey, int $defaultValue, ?EvaluationContext $context = null): ResolutionDetailsInterface
    {
        return $this->evaluate($flagKey, $defaultValue, $context, fn($v) => is_int($v));
    }

    public function resolveFloatValue(string $flagKey, float $defaultValue, ?EvaluationContext $context = null): ResolutionDetailsInterface
    {
        return $this->evaluate($flagKey, $defaultValue, $context, fn($v) => is_float($v) || is_int($v));
    }

    /** @param mixed[] $defaultValue */
    public function resolveObjectValue(string $flagKey, array $defaultValue, ?EvaluationContext $context = null): ResolutionDetailsInterface
    {
        return $this->evaluate($flagKey, $defaultValue, $context);
    }

    public static function parseVariant(string $value): string
    {
        $parts = explode('/', $value, 4);

        if (count($parts) < 4 || $parts[0] !== 'flags' || $parts[2] !== 'variants') {
            throw new \InvalidArgumentException("Invalid variant name: $value");
        }

        return $parts[3];
    }

    private function evaluate(
        string $flagKey,
        mixed $defaultValue,
        ?EvaluationContext $context,
        ?\Closure $validator = null,
    ): ResolutionDetailsInterface {
        $parts = explode('.', $flagKey);
        $flagId = array_shift($parts);
        $valuePath = $parts;
        $contextHash = $this->contextToArray($context);

        $result = $this->apiClient->resolveOne(
            "flags/$flagId",
            $contextHash,
            $this->applyOnResolve,
        );

        if ($result === null) {
            return (new ResolutionDetailsBuilder())
                ->withValue($defaultValue)
                ->withReason('ERROR')
                ->withError(new ResolutionError(ErrorCode::FLAG_NOT_FOUND(), "No active flag '$flagId' found"))
                ->build();
        }

        if ($result->isEmpty()) {
            return (new ResolutionDetailsBuilder())
                ->withValue($defaultValue)
                ->withReason('DEFAULT')
                ->build();
        }

        try {
            $value = $this->valueAtPath($flagKey, $result->value, $valuePath);
        } catch (TypeMismatchError $e) {
            return (new ResolutionDetailsBuilder())
                ->withValue($defaultValue)
                ->withReason('ERROR')
                ->withError(new ResolutionError(ErrorCode::TYPE_MISMATCH(), $e->getMessage()))
                ->build();
        }

        if ($value !== null && $validator !== null && !$validator($value)) {
            return (new ResolutionDetailsBuilder())
                ->withValue($defaultValue)
                ->withReason('ERROR')
                ->withError(new ResolutionError(ErrorCode::TYPE_MISMATCH(), 'value did not match expected type'))
                ->build();
        }

        $value ??= $defaultValue;

        return (new ResolutionDetailsBuilder())
            ->withValue($value)
            ->withVariant(self::parseVariant($result->variant))
            ->withReason('TARGETING_MATCH')
            ->build();
    }

    /**
     * @param string[] $path
     */
    private function valueAtPath(string $flag, mixed $value, array $path): mixed
    {
        if (empty($path)) {
            return $value;
        }

        foreach ($path as $key) {
            if (is_array($value) && array_key_exists($key, $value)) {
                $value = $value[$key];
            } else {
                throw new TypeMismatchError("$flag: invalid path: " . implode('.', $path));
            }
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function contextToArray(?EvaluationContext $context): array
    {
        if ($context === null) {
            return [];
        }

        $result = $context->getAttributes()->toArray();

        $targetingKey = $context->getTargetingKey();
        if ($targetingKey !== null) {
            $result['targeting_key'] = $targetingKey;
        }

        return $result;
    }
}
