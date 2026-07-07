# Confidence OpenFeature PHP Provider

This repo contains the OpenFeature PHP flag provider for [Confidence](https://confidence.spotify.com/).

## Architecture

**Note:** This provider uses the **online resolver** approach. Flag evaluations are resolved by making API calls to the Confidence backend for each evaluation request. This is different from the local resolver approach used in other Confidence providers (Java, JavaScript, Go) which use WebAssembly (WASM) for local flag evaluation.

## OpenFeature

Before starting to use the provider, it can be helpful to read through the general [OpenFeature docs](https://docs.openfeature.dev/)
and get familiar with the concepts.

## Support Matrix

This library supports the same platforms as the [OpenFeature PHP SDK](https://github.com/open-feature/php-sdk).

Requires PHP >= 8.2.

## Installation

Install the package via Composer:

```sh
composer require spotify/confidence-openfeature-provider
```

## Creating and Using the Flag Provider

Below is an example for how to create an OpenFeature client using the Confidence flag provider, and then resolve a flag with a boolean attribute. The provider is configured with a **client secret** and a base URL, which will determine where it will send the resolving requests.

The flag will be applied immediately, meaning that Confidence will count the targeted user as having received the treatment.

You can retrieve attributes on the flag variant using property dot notation, meaning `test-flag.boolean-key` will retrieve the attribute `boolean-key` on the flag `test-flag`.

You can also use only the flag name `test-flag` and retrieve all values as an array with `resolveObjectValue()`.

The flag's schema is validated against the requested data type, and if it doesn't match it will fall back to the default value.

```php
<?php

require_once 'vendor/autoload.php';

use Confidence\OpenFeature\ApiClient;
use Confidence\OpenFeature\ConfidenceProvider;
use Confidence\OpenFeature\Region;
use OpenFeature\implementation\flags\Attributes;
use OpenFeature\implementation\flags\EvaluationContext;
use OpenFeature\OpenFeatureAPI;

// Configure OpenFeature with Confidence provider
$api = OpenFeatureAPI::getInstance();

$apiClient = new ApiClient(
    clientSecret: getenv('CONFIDENCE_CLIENT_SECRET'),
    baseUrl: Region::EU->value,
);
$api->setProvider(new ConfidenceProvider($apiClient));

// Create a client
$client = $api->getClient('my-app');

$context = new EvaluationContext('user-123', new Attributes([
    'country' => 'SE',
    'plan' => 'premium',
]));

$flagValue = $client->getBooleanValue('test-flag.boolean-key', false, $context);

echo "Feature enabled: " . ($flagValue ? 'true' : 'false') . "\n";
```

### Custom Resolver URL

You can point the provider at a local or custom resolver by passing any base URL:

```php
$apiClient = new ApiClient(
    clientSecret: getenv('CONFIDENCE_CLIENT_SECRET'),
    baseUrl: 'http://localhost:8080/v1',
);
```

## Evaluation Context

The evaluation context contains information about the user/session being evaluated for targeting and A/B testing.

```php
$context = new EvaluationContext('user-123', new Attributes([
    'country' => 'US',
    'plan' => 'premium',
    'age' => 25,
]));
```

## Error Handling

**Important**: This provider uses the **online resolver** approach - each flag evaluation makes a network call to Confidence. Proper error handling is critical!

The provider maps errors to standard OpenFeature error codes:

- `FLAG_NOT_FOUND` - The requested flag does not exist or is not active
- `TYPE_MISMATCH` - The flag value type does not match the requested type, or the key path is invalid
- `GENERAL` - Network or API errors

```php
$details = $client->getBooleanDetails('test-flag.enabled', false, $context);

if ($details->getError() !== null) {
    error_log(sprintf(
        'Flag error: %s (%s)',
        $details->getError()->getResolutionErrorMessage(),
        $details->getReason(),
    ));
}
```
