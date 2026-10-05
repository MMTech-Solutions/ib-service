<?php

declare(strict_types=1);

namespace App\Features\Progression\Exceptions;

use App\Support\Exceptions\ApiException;

final class ProgressionArtifactNotFoundException extends ApiException
{
    public static function missing(): self
    {
        return new self('PROGRESSION_ARTIFACT_NOT_FOUND', 'Progression artifact was not found.', 404);
    }
}
