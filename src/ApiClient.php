<?php

declare(strict_types=1);

namespace Confidence\OpenFeature;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class ApiClient implements ApiClientInterface
{
    private Client $httpClient;
    private string $clientSecret;
    private string $baseUrl;

    public function __construct(string $clientSecret, string $baseUrl = Region::EU->value)
    {
        $this->clientSecret = $clientSecret;
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->httpClient = new Client();
    }

    public function resolveOne(string $flag, array $context = [], bool $apply = true): ?ResolvedFlag
    {
        $result = $this->resolve([$flag], $context, $apply);

        if (empty($result)) {
            return null;
        }

        $resolved = $result[0];
        if ($resolved->flag !== $flag) {
            return null;
        }

        return $resolved;
    }

    /**
     * @param string[] $flags
     * @param array<string, mixed> $context
     * @return ResolvedFlag[]
     */
    public function resolve(array $flags, array $context = [], bool $apply = true): array
    {
        try {
            $response = $this->httpClient->post($this->baseUrl . '/flags:resolve', [
                'json' => [
                    'clientSecret' => $this->clientSecret,
                    'evaluationContext' => $context ?: new \stdClass(),
                    'apply' => $apply,
                    'flags' => $flags,
                    'sdk' => [
                        'id' => 'SDK_ID_PHP_PROVIDER',
                        'version' => Version::VERSION,
                    ],
                ],
                'headers' => ['Content-Type' => 'application/json'],
            ]);
        } catch (GuzzleException $e) {
            throw new ApiError('flags:resolve HTTP error: ' . $e->getMessage(), 0, $e);
        }

        $body = (string) $response->getBody();

        try {
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new ApiError('flags:resolve malformed JSON: ' . $e->getMessage(), 0, $e);
        }

        $resolvedFlags = $data['resolvedFlags'] ?? [];

        return array_map(function (array $flag): ResolvedFlag {
            $variant = $flag['variant'] ?? null;
            $value = $flag['value'] ?? null;

            return new ResolvedFlag(
                flag: $flag['flag'],
                variant: ($variant === '' || $variant === null) ? null : $variant,
                value: ($value === '' || $value === null) ? null : $value,
            );
        }, $resolvedFlags);
    }
}
