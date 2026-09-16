<?php

declare(strict_types=1);

namespace Tests\Architecture;

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class UserContextArchitectureTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function forbiddenAuthenticatedUserAccessProvider(): iterable
    {
        yield 'auth helper' => ['/\bauth\s*\(/', 'auth()'];
        yield 'Auth facade' => ['/\bAuth::/', 'Auth facade'];
        yield 'global request user' => ['/\brequest\s*\(\s*\)\s*->\s*user\s*\(/', 'request()->user()'];
        yield 'injected request user' => ['/\$[A-Za-z_][A-Za-z0-9_]*request\s*->\s*user\s*\(/i', 'Request::user()'];
    }

    #[DataProvider('forbiddenAuthenticatedUserAccessProvider')]
    public function test_application_code_does_not_access_laravel_authenticated_user_directly(
        string $pattern,
        string $description,
    ): void {
        $violations = [];

        foreach (File::allFiles(app_path()) as $file) {
            $contents = File::get($file->getPathname());
            if (preg_match($pattern, $contents) === 1) {
                $violations[] = $file->getRelativePathname();
            }
        }

        self::assertSame([], $violations, "Direct {$description} access found; inject UserContext instead.");
    }

    public function test_http_presenters_do_not_depend_on_persistence(): void
    {
        $violations = [];
        $paths = array_filter([
            app_path('Features/Modules/Catalog/Http/V1/Controllers'),
            app_path('Features/Modules/Catalog/Http/V1/Resources'),
            app_path('Features/Plans/Catalog/Http/V1/Controllers'),
            app_path('Features/Plans/Catalog/Http/V1/Resources'),
            app_path('Features/Programs/Catalog/Http/V1/Controllers'),
            app_path('Features/Rules/Catalog/Http/V1/Controllers'),
            app_path('Features/Rules/Assignments/Http/V1/Controllers'),
            app_path('Features/Subscriptions/Catalog/Http/V1/Controllers'),
        ], static fn (string $path): bool => File::isDirectory($path));

        foreach ($paths as $path) {
            foreach (File::allFiles($path) as $file) {
                $contents = File::get($file->getPathname());
                if (str_contains($contents, '\\Contracts\\Repositories\\')
                    || str_contains($contents, '\\Repositories\\')
                    || str_contains($contents, '\\Factories\\')) {
                    $violations[] = $file->getRelativePathname();
                }
            }
        }

        self::assertSame([], $violations, 'HTTP presenters must receive resolved application data.');
    }

    public function test_modules_catalog_does_not_publish_internal_dtos_as_public_contracts(): void
    {
        self::assertFalse(
            File::isDirectory(app_path('Features/Modules/Catalog/Contracts/Data')),
            'Internal Catalog Data objects belong in DTOs, not Catalog/Contracts/Data.',
        );
        self::assertTrue(
            File::exists(app_path('Features/Modules/Contracts/Data/V1/ModuleSummaryData.php')),
            'P1 publishes ModuleSummaryData in Modules/Contracts/Data/V1.',
        );
        self::assertFalse(
            File::isDirectory(app_path('Features/Plans/Catalog/Contracts/Data')),
            'Plans P1 has no inter-feature consumer; its Data objects belong in DTOs.',
        );
        self::assertTrue(
            File::exists(app_path('Features/Programs/Contracts/Data/V1/ProgramContextData.php')),
            'R2 publishes ProgramContextData in Programs/Contracts/Data/V1.',
        );
        self::assertTrue(
            File::exists(app_path('Features/Plans/Contracts/Data/V1/ResolvePlanContextQueryData.php')),
            'Plans publishes ResolvePlanContextQueryData in Contracts/Data/V1.',
        );
        self::assertTrue(
            File::exists(app_path('Features/Plans/Contracts/Data/V1/PlanSubscriptionContextData.php')),
            'Plans publishes PlanSubscriptionContextData in Contracts/Data/V1.',
        );
        self::assertTrue(
            File::exists(app_path('Features/Plans/Contracts/Data/V1/AssertEnabledModuleIdsQueryData.php')),
            'Plans publishes AssertEnabledModuleIdsQueryData in Contracts/Data/V1.',
        );
        self::assertTrue(
            File::exists(app_path('Features/Programs/Contracts/Data/V1/ResolveProgramContextQueryData.php')),
            'Programs publishes ResolveProgramContextQueryData in Contracts/Data/V1.',
        );
        self::assertTrue(
            File::exists(app_path('Features/Programs/Contracts/Data/V1/ProgramSubscriptionContextData.php')),
            'Programs publishes ProgramSubscriptionContextData in Contracts/Data/V1.',
        );
        self::assertTrue(
            File::exists(app_path('Features/Programs/Contracts/Data/V1/AssertSelectedModuleQueryData.php')),
            'Programs publishes AssertSelectedModuleQueryData in Contracts/Data/V1.',
        );
        self::assertFalse(
            File::isDirectory(app_path('Features/Subscriptions/Catalog/Contracts/Data')),
            'Subscriptions has no inter-feature consumer yet; Data objects belong in DTOs.',
        );
        self::assertFalse(
            File::isDirectory(app_path('Features/Rules/Assignments/Contracts/Data')),
            'Rules Assignments has no inter-feature consumer yet; Data objects belong in DTOs.',
        );
    }

    public function test_context_ports_accept_only_contractual_query_data(): void
    {
        $ports = [
            app_path('Features/Plans/Contracts/Ports/Input/ResolvePlanContextPort.php'),
            app_path('Features/Plans/Contracts/Ports/Input/ResolvePlanSubscriptionContextPort.php'),
            app_path('Features/Programs/Contracts/Ports/Input/ResolveProgramContextPort.php'),
            app_path('Features/Programs/Contracts/Ports/Input/ResolveProgramSubscriptionContextPort.php'),
        ];

        foreach ($ports as $portPath) {
            self::assertFileExists($portPath);
            $contents = File::get($portPath);
            self::assertDoesNotMatchRegularExpression(
                '/function\s+\w+\s*\([^)]*\bstring\b/',
                $contents,
                basename($portPath).' must not accept scalar string parameters.',
            );
            self::assertDoesNotMatchRegularExpression(
                '/function\s+\w+\s*\([^)]*\barray\b/',
                $contents,
                basename($portPath).' must not accept bare array parameters.',
            );
            self::assertStringContainsString(
                'Contracts\\Data\\V1\\',
                $contents,
                basename($portPath).' must accept Contracts/Data/V1 query objects.',
            );
        }
    }

    public function test_rules_assignments_do_not_import_programs_internals(): void
    {
        $violations = [];
        $path = app_path('Features/Rules/Assignments');
        if (! File::isDirectory($path)) {
            self::fail('Rules Assignments directory is missing.');
        }

        foreach (File::allFiles($path) as $file) {
            $contents = File::get($file->getPathname());
            if (
                str_contains($contents, 'App\\Features\\Programs\\Catalog\\')
                || str_contains($contents, 'App\\Features\\Modules\\Catalog\\')
            ) {
                $violations[] = $file->getRelativePathname();
            }
        }

        self::assertSame([], $violations, 'Rules Assignments must use Programs/Modules Contracts only.');
    }

    public function test_subscriptions_do_not_import_plans_or_programs_internals(): void
    {
        $violations = [];
        $path = app_path('Features/Subscriptions');
        if (! File::isDirectory($path)) {
            self::fail('Subscriptions directory is missing.');
        }

        foreach (File::allFiles($path) as $file) {
            $contents = File::get($file->getPathname());
            if (
                str_contains($contents, 'App\\Features\\Plans\\Catalog\\')
                || str_contains($contents, 'App\\Features\\Programs\\Catalog\\')
            ) {
                $violations[] = $file->getRelativePathname();
            }
        }

        self::assertSame([], $violations, 'Subscriptions must use Plans/Programs Contracts only.');
    }
}
