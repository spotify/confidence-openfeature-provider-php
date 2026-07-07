<?php

declare(strict_types=1);

namespace Confidence\OpenFeature;

enum Region: string
{
    case EU = 'https://resolver.eu.confidence.dev/v1';
    case US = 'https://resolver.us.confidence.dev/v1';
}
