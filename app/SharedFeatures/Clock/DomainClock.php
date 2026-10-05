<?php

declare(strict_types=1);

namespace App\SharedFeatures\Clock;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use RuntimeException;

final class DomainClock
{
    private ?CarbonImmutable $instant = null;

    /** @var array{context_id: ?string, sequence: ?int} */
    private array $context = ['context_id' => null, 'sequence' => null];

    /** @var resource|null */
    private $lock = null;

    public function __destruct()
    {
        $this->end();
    }

    public function assertLab(): void
    {
        if (! config('lab.profile') || ! app()->environment('local')) {
            throw new RuntimeException('Lab controls require explicit profile and local environment.');
        }
    }

    public function now(): CarbonImmutable
    {
        if ($this->instant === null) {
            $this->begin();
        }

        return $this->instant;
    }

    /** @return array{context_id: ?string, sequence: ?int} */
    public function context(): array
    {
        $this->now();

        return $this->context;
    }

    public function begin(): void
    {
        if ($this->instant !== null) {
            return;
        }
        if (config('lab.failures_enabled')) {
            $this->assertLab();
            if (config('lab.clock_mode') !== 'controlled') {
                throw new RuntimeException('Lab failures require controlled clock.');
            }
        }
        if (config('lab.clock_mode') === 'system') {
            $this->instant = CarbonImmutable::now('UTC');

            return;
        }
        if (config('lab.clock_mode') !== 'controlled') {
            throw new RuntimeException('Invalid domain clock mode.');
        }
        $this->assertLab();
        $this->acquire();
        try {
            $data = $this->read((string) config('lab.clock_file'));
            $this->validate($data);
            $this->accept($data);
            $this->instant = CarbonImmutable::parse($data['now_utc'])->utc();
            $this->context = ['context_id' => $data['context_id'], 'sequence' => $data['sequence']];
        } catch (\Throwable $error) {
            $this->end();
            throw $error;
        }
    }

    public function end(): void
    {
        $this->instant = null;
        $this->context = ['context_id' => null, 'sequence' => null];
        if (is_resource($this->lock)) {
            flock($this->lock, LOCK_UN);
            fclose($this->lock);
        }
        $this->lock = null;
    }

    public function advance(string $contextId, int $sequence, string $nowUtc): void
    {
        $this->assertLab();
        if (config('lab.clock_mode') !== 'controlled') {
            throw new RuntimeException('Controlled clock mode required.');
        }
        $data = ['version' => 1, 'context_id' => $contextId, 'sequence' => $sequence, 'now_utc' => $nowUtc];
        $this->validate($data);
        $this->acquire();
        try {
            $path = (string) config('lab.clock_file');
            if (is_file($path)) {
                $old = $this->read($path);
                $this->validate($old);
                $this->assertAdvance($old, $data);
            }
            $state = $path.'.accepted';
            if (is_file($state)) {
                $this->assertAdvance($this->read($state), $data);
            }
            $this->writeAtomic($path, $data);
            $this->writeAtomic($state, $data);
        } finally {
            $this->end();
        }
    }

    private function acquire(): void
    {
        $path = config('lab.clock_file');
        if (! is_string($path) || $path === '' || ! is_dir(dirname($path))) {
            throw new RuntimeException('Private Lab clock path is required.');
        }
        $directory = realpath(dirname($path));
        $public = realpath(public_path());
        if ($directory === false || ($public !== false && (strtolower($directory) === strtolower($public) || str_starts_with(strtolower($directory), strtolower($public).DIRECTORY_SEPARATOR)))) {
            throw new RuntimeException('Lab clock must be outside the public directory.');
        }
        $this->lock = fopen($path.'.lock', 'c+b');
        if ($this->lock === false || ! flock($this->lock, LOCK_EX | LOCK_NB)) {
            $this->end();
            throw new RuntimeException('Lab clock is busy.');
        }
    }

    /** @param array<string, mixed> $data */
    private function validate(array $data): void
    {
        if (($data['version'] ?? null) !== 1 || ! is_string($data['context_id'] ?? null) || ! Str::isUuid($data['context_id']) || ! is_int($data['sequence'] ?? null) || $data['sequence'] < 0 || ! self::validUtc($data['now_utc'] ?? null)) {
            throw new RuntimeException('Invalid Lab clock document.');
        }
    }

    public static function validUtc(mixed $value): bool
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?Z$/D', $value)) {
            return false;
        }
        try {
            $date = \DateTimeImmutable::createFromFormat(str_contains($value, '.') ? '!Y-m-d\\TH:i:s.u\\Z' : '!Y-m-d\\TH:i:s\\Z', $value, new \DateTimeZone('UTC'));
            $errors = \DateTimeImmutable::getLastErrors();

            return $date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0));
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return array<string, mixed> */
    private function read(string $path): array
    {
        $raw = @file_get_contents($path);
        if ($raw === false) {
            throw new RuntimeException('Lab clock file unavailable.');
        }
        $data = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($data)) {
            throw new RuntimeException('Invalid Lab clock document.');
        }

        return $data;
    }

    /** @param array<string, mixed> $data */
    private function accept(array $data): void
    {
        $state = (string) config('lab.clock_file').'.accepted';
        if (is_file($state)) {
            $this->assertAdvance($this->read($state), $data);
        }
        $this->writeAtomic($state, $data);
    }

    /** @param array<string, mixed> $old @param array<string, mixed> $new */
    private function assertAdvance(array $old, array $new): void
    {
        $this->validate($old);
        if ($old['context_id'] !== $new['context_id'] || $new['sequence'] < $old['sequence'] ||
            CarbonImmutable::parse($new['now_utc'])->lessThan(CarbonImmutable::parse($old['now_utc'])) ||
            ($new['sequence'] === $old['sequence'] && ! CarbonImmutable::parse($new['now_utc'])->equalTo(CarbonImmutable::parse($old['now_utc'])))) {
            throw new RuntimeException('Lab clock context or monotonicity violation.');
        }
    }

    /** @param array<string, mixed> $data */
    private function writeAtomic(string $path, array $data): void
    {
        $temporary = $path.'.'.Str::uuid().'.tmp';
        try {
            if (file_put_contents($temporary, json_encode($data, JSON_THROW_ON_ERROR), LOCK_EX) === false || ! rename($temporary, $path)) {
                throw new RuntimeException('Lab clock atomic write failed.');
            }
            @chmod($path, 0600);
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }
}
