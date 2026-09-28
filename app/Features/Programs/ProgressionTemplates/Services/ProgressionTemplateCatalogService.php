<?php

declare(strict_types=1);

namespace App\Features\Programs\ProgressionTemplates\Services;

use App\Features\Programs\ProgressionTemplates\DTOs\ProgressionTemplateData;
use App\Features\Programs\ProgressionTemplates\DTOs\ProgressionTemplateLevelData;
use App\Features\Programs\ProgressionTemplates\DTOs\ProgressionTemplateVersionData;
use App\Features\Programs\ProgressionTemplates\Exceptions\ProgressionTemplateException;
use App\Features\Programs\ProgressionTemplates\Factories\ProgressionTemplateRepositoryFactory;
use App\Features\Programs\ProgressionTemplates\Http\V1\Commands\ManageProgressionTemplateCommand;
use App\Features\Programs\ProgressionTemplates\Http\V1\Commands\ProgressionTemplateLevelCommandData;
use App\Features\Programs\ProgressionTemplates\Models\ProgressionTemplate;
use App\Features\Programs\ProgressionTemplates\Models\ProgressionTemplateLevel;
use App\Features\Programs\ProgressionTemplates\Models\ProgressionTemplateVersion;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class ProgressionTemplateCatalogService
{
    public function __construct(private readonly ProgressionTemplateRepositoryFactory $factory) {}

    /** @return list<ProgressionTemplateData> */
    public function index(): array
    {
        return array_map(fn (ProgressionTemplate $template): ProgressionTemplateData => $this->data($template), $this->factory->make()->all());
    }

    public function show(string $id): ProgressionTemplateData
    {
        return $this->data($this->template($id));
    }

    public function create(ManageProgressionTemplateCommand $command): ProgressionTemplateData
    {
        $now = CarbonImmutable::now('UTC')->toISOString();
        $template = new ProgressionTemplate((string) Str::uuid7(), $command->name ?? '', $command->description, 1, [], $now, $now);
        $this->factory->make()->save($template);

        return $this->data($template);
    }

    public function update(string $id, ManageProgressionTemplateCommand $command): ProgressionTemplateData
    {
        $repository = $this->factory->make();

        return $repository->transaction(function () use ($repository, $id, $command): ProgressionTemplateData {
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

    public function createVersion(string $id, ManageProgressionTemplateCommand $command): ProgressionTemplateData
    {
        $repository = $this->factory->make();

        return $repository->transaction(function () use ($repository, $id, $command): ProgressionTemplateData {
            $template = $this->template($id);
            $now = CarbonImmutable::now('UTC')->toISOString();
            $number = $template->nextVersionNumber();
            $version = new ProgressionTemplateVersion((string) Str::uuid7(), $template->id, $number, 'draft', null, 1, $this->levels($command->levels ?? []), $now, $now);
            $repository->saveVersion($version, true);

            return $this->show($id);
        });
    }

    public function updateVersion(string $id, string $versionId, ManageProgressionTemplateCommand $command): ProgressionTemplateData
    {
        $repository = $this->factory->make();

        return $repository->transaction(function () use ($repository, $id, $versionId, $command): ProgressionTemplateData {
            $version = $this->version($id, $versionId);
            if (! $version->isDraft()) {
                throw ProgressionTemplateException::immutable($versionId);
            }
            $version->replaceLevels($this->levels($command->levels ?? []), CarbonImmutable::now('UTC')->toISOString());
            $repository->saveVersion($version, false, $command->lockVersion);

            return $this->show($id);
        });
    }

    public function publishVersion(string $id, string $versionId, int $lockVersion): ProgressionTemplateData
    {
        $repository = $this->factory->make();
        $version = $this->version($id, $versionId);
        if (! $version->isDraft()) {
            throw ProgressionTemplateException::immutable($versionId);
        }

        return $repository->transaction(function () use ($repository, $version, $lockVersion, $id): ProgressionTemplateData {
            $version->publish(CarbonImmutable::now('UTC')->toISOString());
            $repository->saveVersion($version, false, $lockVersion);

            return $this->show($id);
        });
    }

    public function deleteVersion(string $id, string $versionId, int $lockVersion): void
    {
        $version = $this->version($id, $versionId);
        if (! $version->isDraft()) {
            throw ProgressionTemplateException::immutable($versionId);
        }
        $this->factory->make()->deleteVersion($version, $lockVersion);
    }

    private function template(string $id): ProgressionTemplate
    {
        return $this->factory->make()->find($id) ?? throw ProgressionTemplateException::notFound($id);
    }

    private function version(string $templateId, string $versionId): ProgressionTemplateVersion
    {
        foreach ($this->template($templateId)->versions as $version) {
            if ($version->id === $versionId) {
                return $version;
            }
        } throw ProgressionTemplateException::versionNotFound($versionId);
    }

    /**
     * @param  list<ProgressionTemplateLevelCommandData>  $levels
     * @return list<ProgressionTemplateLevel>
     */
    private function levels(array $levels): array
    {
        return array_map(
            static fn (ProgressionTemplateLevelCommandData $level): ProgressionTemplateLevel => new ProgressionTemplateLevel(
                id: (string) Str::uuid7(),
                distributionLevel: $level->distributionLevel,
                weight: $level->weight,
            ),
            $levels,
        );
    }

    private function data(ProgressionTemplate $template): ProgressionTemplateData
    {
        return new ProgressionTemplateData(
            id: $template->id,
            name: $template->name,
            description: $template->description,
            lock_version: $template->lockVersion,
            versions: array_map(
                static fn (ProgressionTemplateVersion $version): ProgressionTemplateVersionData => new ProgressionTemplateVersionData(
                    id: $version->id,
                    version_number: $version->versionNumber,
                    status: $version->status,
                    published_at: $version->publishedAt,
                    lock_version: $version->lockVersion,
                    levels: array_map(
                        static fn (ProgressionTemplateLevel $level): ProgressionTemplateLevelData => new ProgressionTemplateLevelData(
                            distribution_level: $level->distributionLevel,
                            weight: $level->weight,
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
