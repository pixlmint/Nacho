<?php

namespace Nacho\Helpers\Log;

use RuntimeException;

class FileLogWriter implements LogWriterInterface
{
    private string $path;

    /** @var ?resource $file */
    private $file = null;

    public function __construct(string $path)
    {
        $this->path = $path;
        $this->open();
    }

    /**
     * @inheritDoc
     */
    public function write(string $message, Level $level, string $context): void
    {
        if (!$this->file) {
            $this->open();
        }
        fwrite($this->file, $message . PHP_EOL);
    }

    /**
     * @inheritDoc
     */
    public function open(): void
    {
        $file = fopen($this->path, 'a');
        if (!$file) {
            throw new RuntimeException('Could not open log file at ' . $this->path);
        }
        $this->file = $file;
    }

    /**
     * @inheritDoc
     */
    public function close(): void
    {
        fclose($this->file);
    }
}