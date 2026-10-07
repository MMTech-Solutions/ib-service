<?php

declare(strict_types=1);

namespace App\Features\Scheduling\Services;

use Symfony\Component\Console\Output\Output;

final class RedactedOutputBuffer extends Output
{
    private string $buffer = '';

    public ?\Closure $onWrite = null;

    public bool $truncated = false;

    /** @param list<string> $secrets */
    public function __construct(private readonly int $limit, private readonly array $secrets = [])
    {
        parent::__construct();
    }

    protected function doWrite(string $message, bool $newline): void
    {
        $this->append($message.($newline ? PHP_EOL : ''));
    }

    public function append(string $message): void
    {
        $remaining = max(0, $this->limit - strlen($this->buffer));
        if (strlen($message) > $remaining) {
            $this->truncated = true;
        }
        $this->buffer .= substr($message, 0, $remaining);
        if ($this->onWrite !== null) {
            ($this->onWrite)();
        }
    }

    public function raw(): string
    {
        return $this->buffer;
    }

    public function redacted(bool $completeLinesOnly = false): string
    {
        $text = $this->buffer;
        if ($this->truncated || $completeLinesOnly) {
            $lastLine = strrpos($text, "\n");
            $text = $lastLine === false ? '' : substr($text, 0, $lastLine + 1);
        }
        foreach ($this->secrets as $secret) {
            if ($secret !== '') {
                $text = str_replace($secret, '[REDACTED]', $text);
            }
        }
        $text = preg_replace('/Bearer\\s+[^\\s"\',;]+/i', 'Bearer [REDACTED]', $text) ?? '';
        $text = preg_replace('/((?:"?(?:password|passwd|secret|token|api[_-]?key|authorization|cookie)"?)\\s*[:=]\\s*)(?:"[^"]*"|\'[^\']*\'|[^\\s,;]+)/i', '$1[REDACTED]', $text) ?? '';
        $text = preg_replace('~https?://[^\\s<>"\']+~i', '[REDACTED_URL]', $text) ?? '';
        $text = preg_replace('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\\.[A-Z]{2,}/i', '[REDACTED_EMAIL]', $text) ?? '';
        $text = mb_convert_encoding(str_replace("\0", '', $text), 'UTF-8', 'UTF-8');
        if (strlen($text) > $this->limit) {
            $this->truncated = true;
        }

        return mb_strcut($text, 0, $this->limit, 'UTF-8');
    }
}
