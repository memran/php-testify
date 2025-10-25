<?php
// tests/run.php

require_once __DIR__ . '/../vendor/autoload.php';

use Testify\Core\ConfigurationManager;

echo "===========================================\n";
echo "🚀 Running PHP-Testify Test Suite\n";
echo "===========================================\n";

// Load configuration
$configManager = new ConfigurationManager();
$config = $configManager->getAll();

echo "📁 Configuration loaded from: phpunit.config.php\n";
echo "🎯 Test directories: " . implode(', ', $config['testSuite']['directories'] ?? ['tests']) . "\n";
echo "===========================================\n";

// Load all test files from configured directories
$testDirs = $config['testSuite']['directories'] ?? ['tests'];
$testFiles = [];

foreach ($testDirs as $dir) {
    $fullDir = __DIR__ . '/../' . $dir;
    if (is_dir($fullDir)) {
        $dirTestFiles = glob($fullDir . '/*.php');
        $testFiles = array_merge($testFiles, $dirTestFiles);
    }
}

foreach ($testFiles as $testFile) {
    if (basename($testFile) !== 'run.php') {
        require_once $testFile;
        echo "✓ Loaded: " . basename($testFile) . "\n";
    }
}

// Run tests
Testify\runTests();
