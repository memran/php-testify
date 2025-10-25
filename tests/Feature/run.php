<?php
// tests/Feature/run.php

require_once __DIR__ . '/../../vendor/autoload.php';

echo "===========================================\n";
echo "🔧 Running Feature Tests\n";
echo "===========================================\n";

// Load feature test files
$featureTestFiles = [
    'IntegrationTest.php',
    'LifecycleTest.php',
    // Add more feature test files
];

foreach ($featureTestFiles as $testFile) {
    $filePath = __DIR__ . '/' . $testFile;
    if (file_exists($filePath)) {
        require_once $filePath;
        echo "✓ Loaded: $testFile\n";
    }
}

$startTime = microtime(true);
Testify\runTests();
$endTime = microtime(true);

echo "===========================================\n";
echo "⏱️  Feature tests completed in: " . round($endTime - $startTime, 2) . "s\n";
echo "===========================================\n";
