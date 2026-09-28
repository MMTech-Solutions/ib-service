<?php

declare(strict_types=1);

namespace App\Features\Programs\PaymentTemplates\Services;

use App\Features\Programs\PaymentTemplates\DTOs\PaymentTemplateData;
use App\Features\Programs\PaymentTemplates\DTOs\PaymentTemplateLevelData;
use App\Features\Programs\PaymentTemplates\DTOs\PaymentTemplateVersionData;
use App\Features\Programs\PaymentTemplates\Exceptions\PaymentTemplateException;
use App\Features\Programs\PaymentTemplates\Factories\PaymentTemplateRepositoryFactory;
use App\Features\Programs\PaymentTemplates\Http\V1\Commands\ManagePaymentTemplateCommand;
use App\Features\Programs\PaymentTemplates\Http\V1\Commands\PaymentTemplateLevelCommandData;
use App\Features\Programs\PaymentTemplates\Models\PaymentTemplate;
use App\Features\Programs\PaymentTemplates\Models\PaymentTemplateLevel;
use App\Features\Programs\PaymentTemplates\Models\PaymentTemplateVersion;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class PaymentTemplateCatalogService
{
    public function __construct(private readonly PaymentTemplateRepositoryFactory $factory) {}

    /** @return list<PaymentTemplateData> */
    public function index(): array
    {
        return array_map(fn (PaymentTemplate $template): PaymentTemplateData => $this->data($template), $this->factory->make()->all());
    }

    public function show(string $id): PaymentTemplateData
    {
        return $this->data($this->template($id));
    }

    public function create(ManagePaymentTemplateCommand $command): PaymentTemplateData
    {
        $now = CarbonImmutable::now('UTC')->toISOString();
        $template = new PaymentTemplate((string) Str::uuid7(), $command->name ?? '', $command->description, 1, [], $now, $now);
        $this->factory->make()->save($template);

        return $this->data($template);
    }

    public function update(string $id, ManagePaymentTemplateCommand $command): PaymentTemplateData
    {
        $repository = $this->factory->make();

        return $repository->transaction(function () use ($repository, $id, $command): PaymentTemplateData {
            $template = $this->template($id);
            $template->updateDetails($command->name, $command->description, CarbonImmutable::now('UTC')->toISOString());
            $repository->save($template, $command->lockVersion);

            return $this->data($template);
        });
    }

    public function delete(string $id, int $lockVersion): void
    {
        $repository = $this->factory->make();
        $repository->delete($this->template($id), $lockVersion);
    }

    public function createVersion(string $id, ManagePaymentTemplateCommand $command): PaymentTemplateData
    {
        $repository = $this->factory->make();

        return $repository->transaction(function () use ($repository, $id, $command): PaymentTemplateData {
            $template = $this->template($id);
            $now = CarbonImmutable::now('UTC')->toISOString();
            $number = $template->nextVersionNumber();
            $version = new PaymentTemplateVersion((string) Str::uuid7(), $template->id, $number, 'draft', null, 1, $this->levels($command->levels ?? []), $now, $now);
            $repository->saveVersion($version, true);

            return $this->show($id);
        });
    }

    public function updateVersion(string $id, string $versionId, ManagePaymentTemplateCommand $command): PaymentTemplateData
    {
        $repository = $this->factory->make();

        return $repository->transaction(function () use ($repository, $id, $versionId, $command): PaymentTemplateData {
            $version = $this->version($id, $versionId);
            if (! $version->isDraft()) {
                throw PaymentTemplateException::immutable($versionId);
            }
            $version->replaceLevels($this->levels($command->levels ?? []), CarbonImmutable::now('UTC')->toISOString());
            $repository->saveVersion($version, false, $command->lockVersion);

            return $this->show($id);
        });
    }

    public function publishVersion(string $id, string $versionId, int $lockVersion): PaymentTemplateData
    {
        $repository = $this->factory->make();
        $version = $this->version($id, $versionId);
        if (! $version->isDraft()) {
            throw PaymentTemplateException::immutable($versionId);
        }

        return $repository->transaction(function () use ($repository, $version, $lockVersion, $id): PaymentTemplateData {
            $version->publish(CarbonImmutable::now('UTC')->toISOString());
            $repository->saveVersion($version, false, $lockVersion);

            return $this->show($id);
        });
    }

    public function deleteVersion(string $id, string $versionId, int $lockVersion): void
    {
        $version = $this->version($id, $versionId);
        if (! $version->isDraft()) {
            throw PaymentTemplateException::immutable($versionId);
        }
        $this->factory->make()->deleteVersion($version, $lockVersion);
    }

    private function template(string $id): PaymentTemplate
    {
        return $this->factory->make()->find($id) ?? throw PaymentTemplateException::notFound($id);
    }

    private function version(string $templateId, string $versionId): PaymentTemplateVersion
    {
        foreach ($this->template($templateId)->versions as $version) {
            if ($version->id === $versionId) {
                return $version;
            }
        } throw PaymentTemplateException::versionNotFound($versionId);
    }

    /**
     * @param  list<PaymentTemplateLevelCommandData>  $levels
     * @return list<PaymentTemplateLevel>
     */
    private function levels(array $levels): array
    {
        return array_map(
            static fn (PaymentTemplateLevelCommandData $level): PaymentTemplateLevel => new PaymentTemplateLevel(
                id: (string) Str::uuid7(),
                distributionLevel: $level->distributionLevel,
                rate: $level->rate,
            ),
            $levels,
        );
    }

    private function data(PaymentTemplate $template): PaymentTemplateData
    {
        return new PaymentTemplateData(
            id: $template->id,
            name: $template->name,
            description: $template->description,
            lock_version: $template->lockVersion,
            versions: array_map(
                static fn (PaymentTemplateVersion $version): PaymentTemplateVersionData => new PaymentTemplateVersionData(
                    id: $version->id,
                    version_number: $version->versionNumber,
                    status: $version->status,
                    published_at: $version->publishedAt,
                    lock_version: $version->lockVersion,
                    levels: array_map(
                        static fn (PaymentTemplateLevel $level): PaymentTemplateLevelData => new PaymentTemplateLevelData(
                            distribution_level: $level->distributionLevel,
                            rate: $level->rate,
                        ),
                        $version->levels,
                    ),
                ),
                $template->versions,
            ),
            created_at: $template->createdAt,
            updated_at: $template->updatedAt,
        );
    }
}
