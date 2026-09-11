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
}
