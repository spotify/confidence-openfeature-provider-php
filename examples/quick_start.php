<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Confidence\OpenFeature\ApiClient;
use Confidence\OpenFeature\ConfidenceProvider;
use Confidence\OpenFeature\Region;
use OpenFeature\implementation\flags\Attributes;
use OpenFeature\implementation\flags\EvaluationContext;
use OpenFeature\OpenFeatureAPI;

// Get client secret from environment variable
$clientSecret = getenv('CONFIDENCE_CLIENT_SECRET') ?: 'CONFIDENCE_CLIENT_SECRET';

// Configure OpenFeature with Confidence provider
$api = OpenFeatureAPI::getInstance();

$apiClient = new ApiClient(
    clientSecret: $clientSecret,
    baseUrl: Region::EU->value,
);
$api->setProvider(new ConfidenceProvider($apiClient));

// Create a client
$client = $api->getClient('quick-start-app');

// Create evaluation context with user information
$context = new EvaluationContext(attributes: new Attributes([
    'user_id' => 'user-123',
    'country' => 'SE',
]));

// Evaluate a boolean flag
echo "Evaluating boolean flag...\n";
$enabled = $client->getBooleanValue('my-flag.enabled', false, $context);
echo "Feature enabled: " . ($enabled ? 'true' : 'false') . "\n";

// Evaluate an object flag
echo "\nEvaluating object flag...\n";
$config = $client->getObjectValue('my-flag', [], $context);
echo "Config: " . json_encode($config) . "\n";

echo "\nDone!\n";
