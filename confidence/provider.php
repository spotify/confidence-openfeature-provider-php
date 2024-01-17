<?php
declare(strict_types=1);

namespace OpenFeature\Confidence\Providers;

require_once 'vendor/autoload.php';

use OpenFeature\implementation\provider\AbstractProvider;
use OpenFeature\implementation\flags\EvaluationContext;
use OpenFeature\implementation\provider\ResolutionDetails;
use OpenFeature\implementation\provider\ResolutionDetailsBuilder;
use OpenFeature\interfaces\flags\EvaluationContext as EvaluationContextInterface;
use OpenFeature\interfaces\flags\FlagValueType;
use OpenFeature\interfaces\provider\ResolutionDetails as ResolutionDetailsInterface;

use function array_merge;
use function is_null;

enum Region: string
{
    case EU = "https://resolver.eu.confidence.dev/v1";
    case US = "https://resolver.us.confidence.dev/v1";
    case GLOBAL = "https://resolver.confidence.dev/v1"
}

class ResolveResult {
    public ?array $value = null;
    public ?string $variant;
    public ?string $reason;
    public string $token;

    function __construct(
        ?array $value,
        ?string $variant,
        ?string $reason,
        string $token
    ) {
        $this->value = $value;
        $this->variant = $variant;
        $this->reason = $reason;
        $this->token = $token;
    }

    function isValid(): bool {
        return (is_null($this->variant) == false) &&
            (is_null($this->value) == false) &&
            (count($this->value) > 0);
    }
}

class ConfidenceProviderExtension extends AbstractProvider
{
    protected static string $NAME = self::class;

    protected ?string $clientSecret = null;
    protected ?Region $region = null;
    protected bool $applyOnResolve = true;

    function __construct(
        string $clientSecret,
        Region $region,
        bool $applyOnResolve = true
    ) {
        $this->clientSecret = $clientSecret;
        $this->region = $region;
        $this->applyOnResolve = $applyOnResolve;
    }

    public function resolveBooleanValue(
        string $flagKey, 
        bool $defaultValue, 
        ?EvaluationContextInterface $evaluationContext = null
    ): ResolutionDetailsInterface {
        return $this->resolveValue($flagKey, FlagValueType::BOOLEAN, $defaultValue, $evaluationContext);
    }

    public function resolveStringValue(
        string $flagKey, 
        string $defaultValue, 
        ?EvaluationContextInterface $evaluationContext = null
    ): ResolutionDetailsInterface {
        return $this->resolveValue($flagKey, FlagValueType::STRING, $defaultValue, $evaluationContext);
    }

    public function resolveIntegerValue(
        string $flagKey, 
        int $defaultValue, 
        ?EvaluationContextInterface $evaluationContext = null
    ): ResolutionDetailsInterface {
        return $this->resolveValue($flagKey, FlagValueType::INTEGER, $defaultValue, $evaluationContext);
    }

    public function resolveFloatValue(
        string $flagKey, 
        float $defaultValue, 
        ?EvaluationContextInterface $evaluationContext = null
    ): ResolutionDetailsInterface {
        return $this->resolveValue($flagKey, FlagValueType::FLOAT, $defaultValue, $evaluationContext);
    }

    public function resolveObjectValue(
        string $flagKey, 
        array $defaultValue, 
        ?EvaluationContextInterface $evaluationContext = null
    ): ResolutionDetailsInterface {
        return $this->resolveValue($flagKey, FlagValueType::OBJECT, $defaultValue, $evaluationContext);
    }

    protected function resolveValue(
        string $flagKey, 
        string $flagType, 
        bool | string | int | float | DateTime | array | null $defaultValue, 
        ?EvaluationContextInterface $evaluationContext = null
    ): ResolutionDetailsInterface {
        $valuePath = null;
        $flagId = $flagKey;
        $context = [];

        if (is_null($evaluationContext)) {
            $evaluationContext = new EvaluationContext();
        }

        if (str_contains($flagKey, ".")) {
            $flagSplit = explode(".", $flagKey);
            $flagId = $flagSplit[0];
            $valuePath = $flagSplit[1];
        }

        array_merge($context, $evaluationContext->getAttributes()->toArray());

        if (is_null($evaluationContext->getTargetingKey()) == false) {
            $context["targeting_key"] = $evaluationContext->getTargetingKey();
        }

        $result = $this->resolve($flagId, $context);
        if ($result->isValid() == false) {
            return new ResolutionDetailsBuilder()
                .withValue($defaultValue)
                .withReason("Default")
                .build();
        }

        $value = $defaultValue;
        if (array_key_exists($valuePath, $result->value)) {
            $value = $result->value[$valuePath];
        }

        return (new ResolutionDetailsBuilder())
            ->withValue($value)
            ->withReason($result->reason)
            ->withVariant($result->variant)
            ->build();
    }

    protected function resolve(
        string $flagName, 
        array $context
    ): ResolveResult {
        $url = $this->region->value."/flags:resolve";
        $requestBody = [
            "clientSecret" => $this->clientSecret,
            "evaluationContext" => $context,
            "apply" => $this->applyOnResolve,
            "flags" => ["flags/".$flagName],
            "sdk" => ["custom_id" => "SDK_ID_PHP_PROVIDER", "version" => "0.0.1"],
        ];

        //open connection
        $ch = curl_init();

        //set the url, number of POST vars, POST data
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestBody));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));

        //So that curl_exec returns the contents of the cURL; rather than echoing it
        curl_setopt($ch,CURLOPT_RETURNTRANSFER, true); 

        //execute post
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode == 404) {
            // There doesn't seem to be an existing OF error for this.
            throw new Exception("Flag not found.");
        }

        $responseBody = json_decode($result);
        $resolvedFlags = [];
        $resolveToken = "";
        $reason = null;

        if (property_exists($responseBody, "resolvedFlags")) {
            $resolvedFlags = $responseBody->resolvedFlags;
        }

        if (property_exists($responseBody, "resolveToken")) {
            $resolveToken = $responseBody->resolveToken;
        }

        if (count($resolvedFlags) == 0) {
            return ResolveResult(null, null, null, $resolveToken);
        }

        $resolvedFlag = $resolvedFlags[0];
        $variant = $resolvedFlag->variant;
        $reason = $resolvedFlag->reason;
        return new ResolveResult(
            (array)$resolvedFlag->value, 
            (strlen($variant) == 0) ? null : $variant,
            $reason,
            $resolveToken
        );
    }
}
?>