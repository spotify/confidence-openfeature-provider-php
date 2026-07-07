<?php

declare(strict_types=1);

namespace Confidence\OpenFeature\Tests;

use Confidence\OpenFeature\ApiClient;
use Confidence\OpenFeature\ConfidenceProvider;
use Confidence\OpenFeature\Region;
use OpenFeature\implementation\flags\Attributes;
use OpenFeature\implementation\flags\EvaluationContext;
use PHPUnit\Framework\TestCase;

class E2eTest extends TestCase
{
    private ConfidenceProvider $provider;

    protected function setUp(): void
    {
        $clientSecret = getenv('CONFIDENCE_CLIENT_SECRET');
        if (!$clientSecret) {
            $this->markTestSkipped('CONFIDENCE_CLIENT_SECRET not set');
        }

        $apiClient = new ApiClient($clientSecret, Region::EU->value);
        $this->provider = new ConfidenceProvider($apiClient);
    }

    public function testShouldEvaluateAFlag(): void
    {
        $context = new EvaluationContext(attributes: new Attributes([
            'targeting_key' => 'test-a',
        ]));

        $result = $this->provider->resolveIntegerValue('web-sdk-e2e-flag.int', 0, $context);

        $this->assertSame(3, $result->getValue());
        $this->assertSame('TARGETING_MATCH', $result->getReason());
        $this->assertSame('control', $result->getVariant());
    }

    public function testShouldEvaluateAFlagWithNullPantsContext(): void
    {
        $context = new EvaluationContext(attributes: new Attributes([
            'targeting_key' => 'test-a',
            'pants' => null,
        ]));

        $result = $this->provider->resolveBooleanValue('web-sdk-e2e-flag-2.enabled', false, $context);

        $this->assertTrue($result->getValue());
        $this->assertSame('TARGETING_MATCH', $result->getReason());
        $this->assertSame('enabled', $result->getVariant());
    }

    public function testShouldReturnDefaultWhenTargetingKeyExceeds100Characters(): void
    {
        $context = new EvaluationContext(attributes: new Attributes([
            'targeting_key' => str_repeat('a', 101),
        ]));

        $result = $this->provider->resolveIntegerValue('web-sdk-e2e-flag.int', 0, $context);

        $this->assertSame(0, $result->getValue());
        $this->assertSame('DEFAULT', $result->getReason());
    }
}
