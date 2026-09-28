<?php

declare(strict_types=1);

namespace App\Features\Programs\PaymentTemplates\Actions;

use App\Features\Programs\PaymentTemplates\Exceptions\PaymentTemplateException;
use App\Features\Programs\PaymentTemplates\Models\PaymentTemplate;
use App\Features\Programs\PaymentTemplates\Models\PaymentTemplateVersion;

final class ResolvePaymentTemplateVersionAction
{
    public function execute(PaymentTemplate $template, string $versionId): PaymentTemplateVersion
    {
        foreach ($template->versions as $version) {
            if ($version->id === $versionId) {
                return $version;
            }
        }

        throw PaymentTemplateException::versionNotFound($versionId);
    }
}
