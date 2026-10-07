<?php

declare(strict_types=1);

namespace App\Features\Scheduling\Services;

use LogicException;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\ConsoleSectionOutput;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;

final class SchedulingConsoleOutput extends Output implements ConsoleOutputInterface
{
    private OutputInterface $errors;

    public function __construct(public readonly RedactedOutputBuffer $stdout, public readonly RedactedOutputBuffer $stderr)
    {
        parent::__construct();
        $this->errors = $stderr;
    }

    protected function doWrite(string $message, bool $newline): void
    {
        $this->stdout->append($message.($newline ? PHP_EOL : ''));
    }

    public function getErrorOutput(): OutputInterface
    {
        return $this->errors;
    }

    public function setErrorOutput(OutputInterface $error): void
    {
        $this->errors = $error;
    }

    public function section(): ConsoleSectionOutput
    {
        throw new LogicException('Interactive console sections are not supported by Scheduling.');
    }
}
