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
    }
}
