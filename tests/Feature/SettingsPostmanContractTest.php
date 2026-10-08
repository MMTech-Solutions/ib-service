<?php

declare(strict_types=1);

namespace Tests\Feature;

use Database\Seeders\LocalRbacSnapshotSeeder;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

final class SettingsPostmanContractTest extends TestCase
{
    public function test_copy_trading_provider_contracts_are_separate_and_complete(): void
    {
        $collection = json_decode(file_get_contents(base_path('ib-service.postman_collection.json')), true, 512, JSON_THROW_ON_ERROR);
        $folder = collect($collection['item'])->firstWhere('name', 'Provider Contracts')['item'][0];
        self::assertSame('Copy Trading', $folder['name']);
        self::assertStringContainsString('Kafka SOLO', $folder['description']);
        self::assertStringContainsString('position_closed', $folder['description']);
        self::assertCount(7, $folder['item']);
        foreach ($folder['item'] as $entry) {
            $request = $entry['request'];
            self::assertStringStartsWith('{{COPY_TRADING_BASE_URL}}/api/copy-trading/v1/internal/', $request['url']['raw']);
            $headers = array_column($request['header'], 'value', 'key');
            self::assertSame('{{COPY_TRADING_INTERNAL_TOKEN}}', $headers['X-Internal-Token']);
            self::assertSame('{{COPY_TRADING_SOURCE_SERVICE}}', $headers['X-Internal-Source']);
            self::assertStringContainsString('REQUERIDO EN COPY TRADING', $request['description']);
            self::assertGreaterThanOrEqual(6, count($entry['response']));
            foreach ($entry['response'] as $example) {
                self::assertSame($request, $example['originalRequest']);
                self::assertIsArray(json_decode($example['body'], true, 512, JSON_THROW_ON_ERROR));
            }
        }
        $variables = array_column($collection['variable'], 'value', 'key');
        self::assertSame('', $variables['COPY_TRADING_INTERNAL_TOKEN']);
    }

    public function test_collection_covers_application_routes_and_operational_health(): void
    {
        $collection = json_decode(file_get_contents(base_path('ib-service.postman_collection.json')), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('https://schema.getpostman.com/json/collection/v2.1.0/collection.json', $collection['info']['schema']);
        $requests = $this->requests($collection['item']);
        $covered = [];
        foreach ($requests as $request) {
            self::assertContains($request['method'], ['GET', 'POST', 'PATCH', 'PUT', 'DELETE']);
            $url = is_array($request['url']) ? $request['url'] : ['path' => explode('/', preg_replace('/^\{\{BASE_URL\}\}\/?/', '', $request['url']))];
            $path = trim(implode('/', $url['path']), '/');
            $covered[] = $request['method'].' '.$this->normalize($path);
            $headers = array_column($request['header'], 'value', 'key');
            if (str_starts_with($path, 'api/ib/v1/admin/')) {
                self::assertSame('{{ADMIN_USERINFO}}', $headers['X-Userinfo']);
                self::assertSame('{{GATEWAY_INTERNAL_SECRET}}', $headers['X-Internal-Gateway']);
            }
            if (str_starts_with($path, 'api/ib/v1/customer/')) {
                self::assertSame('{{CUSTOMER_USERINFO}}', $headers['X-Userinfo']);
            }
            foreach ($url['path'] as $part) {
                if (str_starts_with($part, ':')) {
                    self::assertContains(substr($part, 1), array_column($url['variable'] ?? [], 'key'));
                }
            }
        }
        self::assertSame(0, Artisan::call('route:list', ['--except-vendor' => true, '--json' => true]));
        $routes = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        foreach ($routes as $route) {
            foreach (explode('|', $route['method']) as $method) {
                if ($method !== 'HEAD') {
                    $expected = $method.' '.$this->normalize(trim($route['uri'], '/'));
                    $pattern = '#^'.str_replace('\{\}', '[^/]+', preg_quote($expected, '#')).'$#D';
                    self::assertTrue(array_any($covered, static fn (string $candidate): bool => preg_match($pattern, $candidate) === 1), 'Missing Postman route: '.$method.' '.$route['uri']);
                }
            }
        }
        self::assertContains('GET up', $covered);
        $variables = array_column($collection['variable'], 'value', 'key');
        foreach (array_keys($variables) as $key) {
            self::assertSame(strtoupper($key), $key);
        }
        self::assertSame('', $variables['SETTING_SECRET']);
        foreach (['ADMIN' => LocalRbacSnapshotSeeder::ADMIN_SUB, 'CUSTOMER' => LocalRbacSnapshotSeeder::CUSTOMER_SUB] as $prefix => $subject) {
            $identity = json_decode(base64_decode(strtr($variables[$prefix.'_USERINFO'], '-_', '+/')), true, 512, JSON_THROW_ON_ERROR);
            self::assertSame($subject, $identity['sub']);
        }
    }

    /** @param list<array<string, mixed>> $items @return list<array<string, mixed>> */
    private function requests(array $items): array
    {
        $requests = [];
        foreach ($items as $item) {
            if (isset($item['request'])) {
                $requests[] = $item['request'];
            } else {
                array_push($requests, ...$this->requests($item['item']));
            }
        }

        return $requests;
    }

    private function normalize(string $path): string
    {
        return preg_replace('/\{\{[^}]+\}\}|:[^\/]+|\{[^}]+\}/', '{}', $path);
    }
}
