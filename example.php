<?php
require_once 'vendor/autoload.php';
require_once 'confidence/provider.php';

use OpenFeature\OpenFeatureAPI;
use OpenFeature\implementation\flags\EvaluationContext;
use OpenFeature\Confidence\Providers\ConfidenceProviderExtension;
use OpenFeature\Confidence\Providers\Region;

function example()
{
    $api = OpenFeatureAPI::getInstance();
    
    // configure a provider
    $api->setProvider(new ConfidenceProviderExtension(
        "CLIENT_KEY",
        Region::GLOBAL
    ));

    // create a client
    $client = $api->getClient();
    
    // evaluation context
    $context = new EvaluationContext("example");

    // get a string flag value
    echo $client->getStringValue('php-demoapp.color', "yellow", $context)."\n";
}

example();
?>