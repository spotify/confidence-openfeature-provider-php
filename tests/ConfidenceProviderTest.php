<?php

declare(strict_types=1);

namespace Confidence\OpenFeature\Tests;

use Confidence\OpenFeature\ApiClientInterface;
use Confidence\OpenFeature\ConfidenceProvider;
use Confidence\OpenFeature\Region;
use Confidence\OpenFeature\ResolvedFlag;
use OpenFeature\implementation\flags\Attributes;
use OpenFeature\implementation\flags\EvaluationContext;
use OpenFeature\interfaces\provider\ErrorCode;
use PHPUnit\Framework\TestCase;

class StubApiClient implements ApiClientInterface
{
    /** @var array<int, array{string, array<string, mixed>, bool}> */
    public array $calls = [];

    /** @var array<string, ResolvedFlag> */
    public array $stubs = [];

    public function __construct()
    {
        $this->stubs = [
            'flags/foo' => new ResolvedFlag(
                flag: 'flags/foo',
                variant: 'flags/foo/variants/bar',
                value: ['enabled' => true],
            ),
        ];
    }

    public function resolveOne(string $flag, array $context = [], bool $apply = true): ?ResolvedFlag
    {
        $this->calls[] = [$flag, $context, $apply];

        return $this->stubs[$flag] ?? null;
    }
}

class ConfidenceProviderTest extends TestCase
{
    // -- Region --

    public function testRegionEuHasValue(): void
    {
        $this->assertNotEmpty(Region::EU->value);
        $this->assertStringContainsString('resolver.eu.confidence.dev', Region::EU->value);
    }

    public function testRegionUsHasValue(): void
    {
        $this->assertNotEmpty(Region::US->value);
        $this->assertStringContainsString('resolver.us.confidence.dev', Region::US->value);
    }

    // -- Variant parsing --

    public function testParsesValidVariant(): void
    {
        $this->assertSame('bar', ConfidenceProvider::parseVariant('flags/foo/variants/bar'));
    }

    public function testParsesVariantWithSlash(): void
    {
        $this->assertSame('bar/baz', ConfidenceProvider::parseVariant('flags/foo/variants/bar/baz'));
    }

    /** @dataProvider invalidVariantProvider */
    public function testRejectsInvalidVariant(string $variant): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ConfidenceProvider::parseVariant($variant);
    }

    /** @return array<string, array{string}> */
    public static function invalidVariantProvider(): array
    {
        return [
            'single segment' => ['a'],
            'wrong structure' => ['a/b/c/d'],
            'wrong keyword' => ['flags/foo/variant/bar'],
            'missing variant' => ['flags/foo'],
            'no variant name' => ['flags/foo/variants'],
        ];
    }

    // -- Provider with stub --

    private function createProvider(?StubApiClient $stub = null, bool $applyOnResolve = true): array
    {
        $stub ??= new StubApiClient();
        $provider = new ConfidenceProvider($stub, $applyOnResolve);

        return [$provider, $stub];
    }

    public function testReturnsObject(): void
    {
        [$provider, $stub] = $this->createProvider();

        $result = $provider->resolveObjectValue('foo', []);

        $this->assertSame(['enabled' => true], $result->getValue());
        $this->assertSame('TARGETING_MATCH', $result->getReason());
        $this->assertSame('bar', $result->getVariant());
        $this->assertSame(['flags/foo', [], true], $stub->calls[0]);
    }

    public function testReturnsBoolKeypath(): void
    {
        [$provider, $stub] = $this->createProvider();

        $result = $provider->resolveBooleanValue('foo.enabled', false);

        $this->assertTrue($result->getValue());
        $this->assertSame('TARGETING_MATCH', $result->getReason());
        $this->assertSame('bar', $result->getVariant());
        $this->assertSame(['flags/foo', [], true], $stub->calls[0]);
    }

    public function testPropagatesContext(): void
    {
        [$provider, $stub] = $this->createProvider();
        $ctx = new EvaluationContext('tgt', new Attributes(['abc' => 'def']));

        $provider->resolveObjectValue('foo', [], $ctx);

        $this->assertSame(
            ['flags/foo', ['abc' => 'def', 'targeting_key' => 'tgt'], true],
            $stub->calls[0],
        );
    }

    public function testTypeMismatchReturnsError(): void
    {
        [$provider] = $this->createProvider();

        $result = $provider->resolveStringValue('foo.enabled', 'fallback');

        $this->assertSame('fallback', $result->getValue());
        $this->assertSame('ERROR', $result->getReason());
        $this->assertNotNull($result->getError());
        $this->assertEquals(ErrorCode::TYPE_MISMATCH(), $result->getError()->getResolutionErrorCode());
    }

    public function testInvalidKeypathReturnsError(): void
    {
        [$provider] = $this->createProvider();

        $result = $provider->resolveStringValue('foo.nonexistent', 'fallback');

        $this->assertSame('fallback', $result->getValue());
        $this->assertSame('ERROR', $result->getReason());
        $this->assertNotNull($result->getError());
        $this->assertEquals(ErrorCode::TYPE_MISMATCH(), $result->getError()->getResolutionErrorCode());
    }

    public function testFlagNotFoundReturnsDefault(): void
    {
        [$provider] = $this->createProvider();

        $result = $provider->resolveObjectValue('unknown-flag', ['default' => true]);

        $this->assertSame(['default' => true], $result->getValue());
        $this->assertSame('ERROR', $result->getReason());
        $this->assertNotNull($result->getError());
        $this->assertEquals(ErrorCode::FLAG_NOT_FOUND(), $result->getError()->getResolutionErrorCode());
    }

    public function testEmptyResolveReturnsDefault(): void
    {
        $stub = new StubApiClient();
        $stub->stubs['flags/empty'] = new ResolvedFlag(
            flag: 'flags/empty',
            variant: null,
            value: null,
        );
        [$provider] = $this->createProvider($stub);

        $result = $provider->resolveObjectValue('empty', ['fallback' => true]);

        $this->assertSame(['fallback' => true], $result->getValue());
        $this->assertSame('DEFAULT', $result->getReason());
    }

    public function testApplyOnResolvePassedToApiClient(): void
    {
        [$provider, $stub] = $this->createProvider(applyOnResolve: false);

        $provider->resolveObjectValue('foo', []);

        $this->assertFalse($stub->calls[0][2]);
    }

    public function testResolveIntegerValue(): void
    {
        $stub = new StubApiClient();
        $stub->stubs['flags/nums'] = new ResolvedFlag(
            flag: 'flags/nums',
            variant: 'flags/nums/variants/v1',
            value: ['count' => 42],
        );
        [$provider] = $this->createProvider($stub);

        $result = $provider->resolveIntegerValue('nums.count', 0);

        $this->assertSame(42, $result->getValue());
        $this->assertSame('TARGETING_MATCH', $result->getReason());
    }

    public function testResolveFloatValue(): void
    {
        $stub = new StubApiClient();
        $stub->stubs['flags/nums'] = new ResolvedFlag(
            flag: 'flags/nums',
            variant: 'flags/nums/variants/v1',
            value: ['ratio' => 3.14],
        );
        [$provider] = $this->createProvider($stub);

        $result = $provider->resolveFloatValue('nums.ratio', 0.0);

        $this->assertSame(3.14, $result->getValue());
        $this->assertSame('TARGETING_MATCH', $result->getReason());
    }

    public function testResolveStringValue(): void
    {
        $stub = new StubApiClient();
        $stub->stubs['flags/text'] = new ResolvedFlag(
            flag: 'flags/text',
            variant: 'flags/text/variants/v1',
            value: ['message' => 'hello'],
        );
        [$provider] = $this->createProvider($stub);

        $result = $provider->resolveStringValue('text.message', '');

        $this->assertSame('hello', $result->getValue());
        $this->assertSame('TARGETING_MATCH', $result->getReason());
    }

    public function testMetadataName(): void
    {
        [$provider] = $this->createProvider();

        $this->assertSame('Confidence', $provider->getMetadata()->getName());
    }
}
