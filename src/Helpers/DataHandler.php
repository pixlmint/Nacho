<?php

namespace Nacho\Helpers;

use Nacho\Contracts\ArrayableInterface;
use Nacho\Contracts\SingletonInterface;

class DataHandler implements SingletonInterface
{
    protected static ?DataHandler $instance = null;
    private array $data = [];

    public static function getInstance(): ?DataHandler
    {
        if (!static::$instance) {
            static::$instance = new DataHandler();
        }

        return static::$instance;
    }

    public static function getDataDir(): string
    {
        return $_SERVER['DOCUMENT_ROOT'] . '/data';
    }

    public function readData(string $dt): array
    {
        if (!$this->isDataCached($dt)) {
            $this->data[$dt] = $this->fetchData($dt);
        }

        return $this->data[$dt];
    }

    public function writeData(string $dt, array $data): void
    {
        $this->data[$dt] = $data;
    }

    public function storeAllData(): void
    {
        foreach ($this->data as $dt => $arr) {
            $this->storeData($dt, $arr);
        }
    }

    public function removeElement(string $dt, mixed $element): void
    {
        $data = $this->readData($dt);
        if (!in_array($element, $data)) {
            return;
        }
        $index = array_search($element, $data);
        $removed = array_slice($data, $index, $index + 1);
        $this->writeData($dt, $data);
    }

    public function addElement(string $dt, mixed $element): void
    {
        $data = $this->readData($dt);
        $data[] = $element;
        $this->writeData($dt, $data);
    }

    protected function storeData(string $dt, array $data): void
    {
        file_put_contents(self::getFileName($dt), json_encode(static::serializeData($data)));
    }

    protected static function serializeData(array $data): array
    {
        return array_map(function ($el) {
            if ($el instanceof ArrayableInterface) {
                return $el->toArray();
            }
            if (is_array($el)) {
                return $el;
            }
            throw new \Exception("Unable to serialize an element. Please either make it an array or have it implement the ArrayableInterface interface");
        }, $data);
    }

    private function fetchData(string $dt): array
    {
        if (!is_file(self::getFileName($dt))) {
            throw new \Exception('The ' . $dt . ' file does not exist');
        }

        return json_decode(file_get_contents(self::getFileName($dt)), true);
    }

    protected static function getFileName(string $dt): string
    {
        return self::getDataDir() . DIRECTORY_SEPARATOR . $dt . '.json';
    }

    private function isDataCached(string $dt): bool
    {
        return key_exists($dt, $this->data);
    }
}