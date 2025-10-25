<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Testify\Core\LifecycleManager;
use Testify\Core\TestSuite;
use Testify\Core\TestCaseFactory;
use Testify\PHPTestify;
use function Testify\{
    describe,
    test,
    it,
    beforeEach,
    afterEach,
    beforeAll,
    afterAll,
    expect,
    runTests
};
// Example of dependency injection and custom configuration
$lifecycleManager = new LifecycleManager();
$testSuite = new TestSuite();
$testCaseFactory = new TestCaseFactory();

$customTestify = new PHPTestify($testSuite, $lifecycleManager, $testCaseFactory);

// Now you can use the custom instance or test individual components
describe('SOLID Principles Demo', function () {
    beforeEach(function () {
        // This demonstrates the lifecycle manager working
    });

    test('dependency injection works', function () {
        expect(true)->toBeTrue();
    });
});

runTests();
