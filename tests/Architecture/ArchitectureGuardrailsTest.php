<?php

declare(strict_types=1);

namespace Tests\Architecture;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

final class ArchitectureGuardrailsTest extends TestCase
{
    /**
     * Historical input-port implementations whose published contract predates
     * the execute() convention. New entries require an explicit architecture decision.
     *
     * @var array<string, array{methods: list<string>, reason: string}>
     */
    private const LEGACY_USE_CASE_METHODS = [
        'Features/Modules/Catalog/UseCases/ListCpaEvidenceUseCase.php' => ['methods' => ['list'], 'reason' => 'Published CPA evidence input port.'],
        'Features/Modules/Catalog/UseCases/ListProgressionActivitiesUseCase.php' => ['methods' => ['list'], 'reason' => 'Published progression activity input port.'],
        'Features/Modules/Catalog/UseCases/ResolveModulesUseCase.php' => ['methods' => ['findByIds', 'assertSelectable'], 'reason' => 'Published module catalog input port.'],
        'Features/Plans/Catalog/UseCases/IsModuleReferencedUseCase.php' => ['methods' => ['isReferenced'], 'reason' => 'Published module reference input port.'],
        'Features/Plans/Catalog/UseCases/ListActivePlansForProgressionUseCase.php' => ['methods' => ['list'], 'reason' => 'Published progression input port.'],
        'Features/Plans/Catalog/UseCases/LockPlanRowsUseCase.php' => ['methods' => ['lockAscending'], 'reason' => 'Published transaction coordination input port.'],
        'Features/Plans/Catalog/UseCases/ResolvePlanContextUseCase.php' => ['methods' => ['resolve', 'assertEnabledModuleIds'], 'reason' => 'Published plan context input port.'],
        'Features/Plans/Catalog/UseCases/ResolvePlanProgressionContextUseCase.php' => ['methods' => ['resolve'], 'reason' => 'Published progression context input port.'],
        'Features/Plans/Catalog/UseCases/ResolvePlanSubscriptionContextUseCase.php' => ['methods' => ['resolve'], 'reason' => 'Published subscription context input port.'],
        'Features/Programs/Catalog/UseCases/ResolveProgramContextUseCase.php' => ['methods' => ['resolve', 'assertSelectedModule'], 'reason' => 'Published program context input port.'],
        'Features/Programs/Catalog/UseCases/ResolveProgramCpaSymbolsUseCase.php' => ['methods' => ['resolve'], 'reason' => 'Published CPA symbols input port.'],
        'Features/Programs/Catalog/UseCases/ResolveProgramProgressionConfigurationUseCase.php' => ['methods' => ['resolve'], 'reason' => 'Published progression configuration input port.'],
        'Features/Programs/Catalog/UseCases/ResolveProgramSubscriptionContextUseCase.php' => ['methods' => ['assertBelongsToPlan', 'resolveFirstByPosition'], 'reason' => 'Published subscription context input port.'],
        'Features/Programs/Catalog/UseCases/ResolveProgressionTargetProgramUseCase.php' => ['methods' => ['resolve'], 'reason' => 'Published progression target input port.'],
        'Features/Rules/Assignments/UseCases/ResolveCpaRuleContextUseCase.php' => ['methods' => ['resolve'], 'reason' => 'Published CPA rule context input port.'],
        'Features/Rules/Assignments/UseCases/ResolvePointsContributionContextUseCase.php' => ['methods' => ['resolve'], 'reason' => 'Published points contribution input port.'],
        'Features/Subscriptions/Catalog/UseCases/ApplyProgressionPlacementUseCase.php' => ['methods' => ['apply'], 'reason' => 'Published progression placement input port.'],
        'Features/Subscriptions/Catalog/UseCases/HasOpenSubscriptionsForPlanUseCase.php' => ['methods' => ['hasOpen'], 'reason' => 'Published subscription input port.'],
        'Features/Subscriptions/Catalog/UseCases/ListProgressionWindowSubscriptionsUseCase.php' => ['methods' => ['list'], 'reason' => 'Published progression window input port.'],
        'Features/Subscriptions/Catalog/UseCases/ResolveSubscriptionContextUseCase.php' => ['methods' => ['resolve'], 'reason' => 'Published subscription context input port.'],
    ];

    /**
     * @var array<string, array{connection_import: int, db_facade: int, query_builder: int, eloquent: int, table_calls: int, reason: string}>
     */
    private const PERSISTENCE_BASELINE = [
        'Features/Programs/Catalog/UseCases/ReplaceProgramSymbolConfigurationsUseCase.php' => ['connection_import' => 1, 'db_facade' => 0, 'query_builder' => 0, 'eloquent' => 0, 'table_calls' => 7, 'reason' => 'M5 atomic replacement awaiting repository extraction.'],
        'Features/Programs/Catalog/UseCases/ResolveProgramCpaSymbolsUseCase.php' => ['connection_import' => 1, 'db_facade' => 0, 'query_builder' => 0, 'eloquent' => 0, 'table_calls' => 1, 'reason' => 'RWD2 program CPA symbols read port awaiting repository extraction.'],
        'Features/Programs/Catalog/UseCases/ResolveProgramProgressionConfigurationUseCase.php' => ['connection_import' => 1, 'db_facade' => 0, 'query_builder' => 0, 'eloquent' => 0, 'table_calls' => 3, 'reason' => 'PG configuration read port awaiting repository extraction.'],
        'Features/Rules/Assignments/UseCases/ResolveCpaRuleContextUseCase.php' => ['connection_import' => 1, 'db_facade' => 0, 'query_builder' => 0, 'eloquent' => 0, 'table_calls' => 1, 'reason' => 'RWD2 CPA rule context read port awaiting repository extraction.'],
    ];

    /** @var array<string, string> */
    private const TECHNICAL_EXCEPTION_BASELINE = [
        'Features/Modules/Catalog/Exceptions/DuplicateModuleCodeException.php' => 'Historical persistence conflict translation.',
        'Features/Modules/Catalog/Exceptions/UnsupportedRewardEvidenceProviderException.php' => 'Internal evidence provider configuration failure; not an HTTP error.',
        'Features/Plans/Catalog/Exceptions/DuplicatePlanCodeException.php' => 'Historical persistence conflict translation.',
        'Features/Programs/Catalog/Exceptions/DuplicateProgramCodeException.php' => 'Historical persistence conflict translation.',
        'Features/Rewards/Exceptions/CpaCaptureNotApplicableException.php' => 'Kafka control-flow outcome; not an HTTP error.',
        'Features/Rewards/Exceptions/RewardSettlementException.php' => 'Retryable Finance adapter failure; not an HTTP error.',
        'Features/Rewards/Exceptions/UnsupportedCpaRewardCalculationStrategyException.php' => 'Internal calculation factory configuration failure; not an HTTP error.',
        'Features/Rewards/Exceptions/UnsupportedNegativePnlPeriodsProviderException.php' => 'Internal PnL provider configuration failure; not an HTTP error.',
        'Features/Rewards/Exceptions/UnsupportedNegativePnlRewardCalculationStrategyException.php' => 'Internal calculation factory configuration failure; not an HTTP error.',
        'Features/Rewards/Exceptions/UnsupportedVolumeRewardCalculationStrategyException.php' => 'Internal calculation factory configuration failure; not an HTTP error.',
        'Features/Rules/Catalog/Exceptions/DuplicateRuleNameException.php' => 'Historical persistence conflict translation.',
        'Features/Rules/Catalog/Exceptions/DuplicateRuleSlugException.php' => 'Historical persistence conflict translation.',
    ];

    public function test_non_port_use_cases_expose_only_execute_and_legacy_port_methods_do_not_expand(): void
    {
        $actualLegacy = [];
        $violations = [];

        foreach ($this->featureFiles('UseCases', 'UseCase.php') as $file) {
            $relative = $this->relativePath($file->getPathname());
            $methods = $this->publicMethods(File::get($file->getPathname()));
            if ($methods === ['execute']) {
                continue;
            }

            $actualLegacy[$relative] = $methods;
            if (! array_key_exists($relative, self::LEGACY_USE_CASE_METHODS)) {
                $violations[] = "{$relative}: ".implode(', ', $methods);
            }
        }

        self::assertSame(array_keys(self::LEGACY_USE_CASE_METHODS), array_keys($actualLegacy), 'The legacy UseCase baseline must be exact; add no implicit exclusions.');
        foreach (self::LEGACY_USE_CASE_METHODS as $path => $baseline) {
            self::assertSame($baseline['methods'], $actualLegacy[$path], "{$path} changed its public input-port methods.");
        }
        self::assertSame([], $violations, 'New UseCases must expose only execute().');
    }

    public function test_application_layers_do_not_expand_direct_persistence_access(): void
    {
        $actualBaseline = [];
        $violations = [];

        foreach ($this->applicationLayerFiles() as $file) {
            $relative = $this->relativePath($file->getPathname());
            $vector = $this->persistenceVector(File::get($file->getPathname()));
            if (array_sum($vector) === 0) {
                continue;
            }
            if (! array_key_exists($relative, self::PERSISTENCE_BASELINE)) {
                $violations[] = "{$relative}: ".json_encode($vector, JSON_THROW_ON_ERROR);

                continue;
            }
            $actualBaseline[$relative] = $vector;
        }

        self::assertSame(array_keys(self::PERSISTENCE_BASELINE), array_keys($actualBaseline), 'The persistence baseline must be exact.');
        foreach (self::PERSISTENCE_BASELINE as $path => $baseline) {
            unset($baseline['reason']);
            self::assertSame($baseline, $actualBaseline[$path], "{$path} expanded direct persistence access.");
        }
        self::assertSame([], $violations, 'UseCases, Actions, Controllers and Resources must not access persistence directly.');
    }

    public function test_repository_factories_only_expose_make(): void
    {
        foreach (File::allFiles(app_path('Features')) as $file) {
            if (! str_ends_with($file->getFilename(), 'RepositoryFactory.php')) {
                continue;
            }
            self::assertSame(['make'], $this->publicMethods(File::get($file->getPathname())), $this->relativePath($file->getPathname()).' must expose only make().');
        }
    }

    public function test_http_presenters_do_not_depend_on_persistence_or_translate_domain_errors(): void
    {
        $violations = [];
        foreach ($this->httpPresenterFiles() as $file) {
            $contents = File::get($file->getPathname());
            $relative = $this->relativePath($file->getPathname());
            if ($this->hasForbiddenPresenterDependency($contents)) {
                $violations[] = "{$relative}: persistence dependency";
            }
            if (str_contains($contents, 'ValidationException') || str_contains($contents, 'InvalidArgumentException') || preg_match('/\$this\s*->\s*error\s*\(/', $contents) === 1) {
                $violations[] = "{$relative}: domain error translation";
            }
        }

        self::assertSame([], $violations, 'HTTP presenters must receive resolved data and let ApiException reach the global normalizer.');
    }

    public function test_feature_exceptions_are_http_api_exceptions_or_explicit_technical_baseline_entries(): void
    {
        $actualTechnical = [];
        $violations = [];
        foreach (File::allFiles(app_path('Features')) as $file) {
            if (! str_ends_with($file->getFilename(), 'Exception.php')) {
                continue;
            }
            $relative = $this->relativePath($file->getPathname());
            $contents = File::get($file->getPathname());
            if (str_contains($contents, 'extends ApiException')) {
                continue;
            }
            if (array_key_exists($relative, self::TECHNICAL_EXCEPTION_BASELINE) && str_contains($contents, 'extends RuntimeException')) {
                $actualTechnical[] = $relative;

                continue;
            }
            $violations[] = $relative;
        }

        self::assertSame(array_keys(self::TECHNICAL_EXCEPTION_BASELINE), $actualTechnical, 'The technical exception baseline must be exact.');
        self::assertSame([], $violations, 'Feature exceptions must extend ApiException unless explicitly technical.');
    }

    public function test_guardrail_detectors_reject_new_violations(): void
    {
        self::assertSame(['resolve'], $this->publicMethods('<?php final class ExampleUseCase { public function resolve(): void {} }'));
        self::assertSame(['connection_import' => 1, 'db_facade' => 0, 'query_builder' => 0, 'eloquent' => 0, 'table_calls' => 1], $this->persistenceVector('<?php use Illuminate\\Database\\ConnectionInterface; $connection->table("rewards");'));
        self::assertTrue($this->hasForbiddenPresenterDependency('<?php use App\\Features\\Rewards\\Repositories\\PostgreSql\\PostgreSqlRewardRepository;'));
        self::assertTrue(str_contains('<?php throw ValidationException::withMessages([]);', 'ValidationException'));
    }

    /** @return list<\SplFileInfo> */
    private function featureFiles(string $directory, string $suffix): array
    {
        return array_values(array_filter(File::allFiles(app_path('Features')), static fn (\SplFileInfo $file): bool => str_contains(str_replace('\\', '/', $file->getPathname()), "/{$directory}/") && str_ends_with($file->getFilename(), $suffix)));
    }

    /** @return list<\SplFileInfo> */
    private function applicationLayerFiles(): array
    {
        return array_values(array_filter(File::allFiles(app_path('Features')), static fn (\SplFileInfo $file): bool => preg_match('#/(UseCases|Actions|Http/V1/(Controllers|Resources))/.+\.php$#', str_replace('\\', '/', $file->getPathname())) === 1));
    }

    /** @return list<\SplFileInfo> */
    private function httpPresenterFiles(): array
    {
        return array_values(array_filter(File::allFiles(app_path('Features')), static fn (\SplFileInfo $file): bool => preg_match('#/Http/V1/(Controllers|Resources)/.+\.php$#', str_replace('\\', '/', $file->getPathname())) === 1));
    }

    /** @return list<string> */
    private function publicMethods(string $contents): array
    {
        preg_match_all('/public\s+function\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(/', $contents, $matches);

        return array_values(array_filter($matches[1], static fn (string $method): bool => $method !== '__construct'));
    }

    /** @return array{connection_import: int, db_facade: int, query_builder: int, eloquent: int, table_calls: int} */
    private function persistenceVector(string $contents): array
    {
        return [
            'connection_import' => preg_match_all('/use\s+Illuminate\\\\Database\\\\ConnectionInterface;/', $contents),
            'db_facade' => preg_match_all('/(?:use\s+Illuminate\\\\Support\\\\Facades\\\\DB;|\\bDB\s*::)/', $contents),
            'query_builder' => preg_match_all('/use\s+Illuminate\\\\Database\\\\Query\\\\/', $contents),
            'eloquent' => preg_match_all('/(?:use\s+Illuminate\\\\Database\\\\Eloquent\\\\|\\b(?:Model|Builder)\b)/', $contents),
            'table_calls' => preg_match_all('/->table\s*\(/', $contents),
        ];
    }

    private function hasForbiddenPresenterDependency(string $contents): bool
    {
        return preg_match('/\\\\(?:Contracts\\\\Repositories|Repositories|Factories)\\\\|use\s+Illuminate\\\\Database\\\\|->table\s*\(|\\bDB\s*::/', $contents) === 1;
    }

    private function relativePath(string $path): string
    {
        return str_replace('\\', '/', substr($path, strlen(app_path()) + 1));
    }
}
