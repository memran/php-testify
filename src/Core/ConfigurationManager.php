<?php

namespace Testify\Core;

class ConfigurationManager
{
    private array $configuration;

    public function __construct(?string $configFile = null)
    {
        $this->configuration = $this->loadConfiguration($configFile);
    }

    private function loadConfiguration(?string $configFile = null): array
    {
        if ($configFile && file_exists($configFile)) {
            return require $configFile;
        }

        // Try default locations
        $defaultLocations = [
            __DIR__ . '/../../phpunit.config.php',
            __DIR__ . '/../../test.config.php',
            getcwd() . '/phpunit.config.php',
            getcwd() . '/test.config.php',
        ];

        foreach ($defaultLocations as $location) {
            if (file_exists($location)) {
                return require $location;
            }
        }

        return $this->getDefaultConfiguration();
    }

    private function getDefaultConfiguration(): array
    {
        return [
            'bootstrap' => 'vendor/autoload.php',
            'colors' => 'always',
            'stopOnFailure' => false,
        ];
    }

    public function get(string $key, $default = null)
    {
        return $this->configuration[$key] ?? $default;
    }

    public function set(string $key, $value): void
    {
        $this->configuration[$key] = $value;
    }

    public function getAll(): array
    {
        return $this->configuration;
    }

    public function merge(array $newConfig): void
    {
        $this->configuration = array_merge($this->configuration, $newConfig);
    }
}
