<?php

namespace Nacho\Helpers;

use Nacho\Contracts\SingletonInterface;

class ConfigurationHelper implements SingletonInterface
{
    private array $config;

    private array $routes = [];
    private array $hooks = [];

    private static ?SingletonInterface $instance = null;

    public function __construct()
    {
        $this->config = include_once($_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'config.php');
        $this->bootstrapRoutes();
        $this->bootstrapHooks();
    }

    private function bootstrapRoutes()
    {
        $this->routes = $this->config['routes'];
    }

    private function bootstrapHooks()
    {
        if (key_exists('hooks', $this->config)) {
            $this->hooks = $this->config['hooks'];
        }
    }

    /**
     * @return SingletonInterface|ConfigurationHelper
     */
    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new ConfigurationHelper();
        }

        return self::$instance;
    }

    public function getHooks(): array
    {
        return $this->hooks;
    }

    public function getRoutes(): array
    {
        return $this->routes;
    }
}