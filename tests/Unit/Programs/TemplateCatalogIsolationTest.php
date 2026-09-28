<?php

declare(strict_types=1);

namespace Tests\Unit\Programs;

use App\Features\Programs\PaymentTemplates\Exceptions\PaymentTemplateException;
use App\Features\Programs\PaymentTemplates\Models\PaymentTemplate;
use App\Features\Programs\PaymentTemplates\Models\PaymentTemplateVersion;
use App\Features\Programs\PaymentTemplates\Repositories\InMemory\InMemoryPaymentTemplateRepository;
use App\Features\Programs\ProgressionTemplates\Models\ProgressionTemplate;
use App\Features\Programs\ProgressionTemplates\Repositories\InMemory\InMemoryProgressionTemplateRepository;
use Tests\TestCase;

final class TemplateCatalogIsolationTest extends TestCase
{
    public function test_payment_and_progression_catalogs_are_isolated(): void
    {
        $payment = new InMemoryPaymentTemplateRepository;
        $progression = new InMemoryProgressionTemplateRepository;
        $payment->save(new PaymentTemplate('payment-1', 'Same name', null, 1, [], 'now', 'now'));
        $progression->save(new ProgressionTemplate('progression-1', 'Same name', null, 1, [], 'now', 'now'));

        self::assertCount(1, $payment->all());
        self::assertCount(1, $progression->all());
    }

    public function test_published_payment_version_cannot_be_changed_or_deleted(): void
    {
        $repository = new InMemoryPaymentTemplateRepository;
        $template = new PaymentTemplate('payment-1', 'Rates', null, 1, [], 'now', 'now');
        $repository->save($template);
        $version = new PaymentTemplateVersion('version-1', $template->id, 1, 'published', 'now', 1, [], 'now', 'now');
        $repository->saveVersion($version, true);

        $this->expectException(PaymentTemplateException::class);
        $repository->deleteVersion($version, 1);
    }
}
