<?php
// tests/Unit/run.php

require_once __DIR__ . '/../../vendor/autoload.php';

echo "===========================================\n";
echo "🧪 Running Unit Tests\n";
echo "===========================================\n";

// Load unit test files
$unitTestFiles = [
    'ExpectationTest.php',
    'CoreTest.php',
    // Add more unit test files
];

foreach ($unitTestFiles as $testFile) {
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
echo "⏱️  Unit tests completed in: " . round($endTime - $startTime, 2) . "s\n";
echo "===========================================\n";
