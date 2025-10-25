<?php

namespace Testify;

use PHPUnit\Framework\TestSuite as PHPUnitTestSuite;
use PHPUnit\TextUI\TestRunner as PHPUnitTestRunner;
use PHPUnit\TextUI\Configuration\Configuration;
use Testify\Core\{TestSuite, TestReporter, TestCaseFactory, LifecycleManager};
use Testify\Core\Contracts\TestSuiteInterface;
use Testify\Core\Contracts\TestRunnerInterface;
use Testify\Core\Contracts\LifecycleManagerInterface;

class PHPTestify implements TestRunnerInterface
{
    private static ?self $instance = null;
    private TestSuiteInterface $testSuite;
    private LifecycleManagerInterface $lifecycleManager;
    private TestCaseFactory $testCaseFactory;
    private TestReporter $testReporter;
    private string $currentDescribe = '';
    private array $testResults = [];
    private float $startTime = 0;
    private array $configuration;

    public function __construct(
        ?TestSuiteInterface $testSuite = null,
        ?LifecycleManagerInterface $lifecycleManager = null,
        ?TestCaseFactory $testCaseFactory = null,
        ?TestReporter $testReporter = null,
        ?array $configuration = null
    ) {
        $this->testSuite = $testSuite ?? new TestSuite();
        $this->lifecycleManager = $lifecycleManager ?? new LifecycleManager();
        $this->testCaseFactory = $testCaseFactory ?? new TestCaseFactory();
        $this->testReporter = $testReporter ?? new TestReporter();
        $this->configuration = $configuration ?? $this->loadDefaultConfiguration();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function describe(string $description, callable $callback): void
    {
        $this->currentDescribe = $description;
        $this->testReporter->reportStart($description);
        $callback();
        $this->currentDescribe = '';
    }

    public function test(string $description, callable $callback): void
    {
        $fullTestName = $this->currentDescribe ? "{$this->currentDescribe} - {$description}" : $description;
        $this->testSuite->addTest($callback, $fullTestName);
    }

    public function it(string $description, callable $callback): void
    {
        $this->test($description, $callback);
    }

    public function beforeEach(callable $callback): void
    {
        $this->lifecycleManager->addBeforeEach($callback);
    }

    public function afterEach(callable $callback): void
    {
        $this->lifecycleManager->addAfterEach($callback);
    }

    public function beforeAll(callable $callback): void
    {
        $this->lifecycleManager->addBeforeAll($callback);
    }

    public function afterAll(callable $callback): void
    {
        $this->lifecycleManager->addAfterAll($callback);
    }

    public function expect($value): Expectation
    {
        return new Expectation($value);
    }

    public function run(): void
    {
        $this->startTime = microtime(true);
        $this->testResults = ['passed' => 0, 'failed' => 0];

        $this->lifecycleManager->executeBeforeAll();

        $phpUnitSuite = new PHPUnitTestSuite();

        foreach ($this->testSuite->getTests() as $test) {
            $testCase = $this->testCaseFactory->create(
                $test['description'],
                $test['callback'],
                $this->lifecycleManager
            );
            $phpUnitSuite->addTest($testCase);
        }

        $configuration = $this->createPHPUnitConfiguration();
        $runner = new PHPUnitTestRunner();

        ob_start();
        $result = $runner->run($phpUnitSuite, $configuration);
        $output = ob_get_clean();

        $this->processTestResults($result, $output);

        $this->lifecycleManager->executeAfterAll();

        $this->reportFinalSummary();

        exit($result->wasSuccessful() ? 0 : 1);
    }

    private function loadDefaultConfiguration(): array
    {
        $configFile = __DIR__ . '/../../phpunit.config.php';
        if (file_exists($configFile)) {
            return require $configFile;
        }

        // Fallback to minimal configuration
        return [
            'bootstrap' => 'vendor/autoload.php',
            'colors' => 'always',
            'verbose' => true,
            'stopOnFailure' => false
        ];
    }

    private function createPHPUnitConfiguration(): array
    {
        $config = [
            'extensions' => [],
            'backupGlobals' => $this->configuration['backupGlobals'] ?? false,
            'backupStaticAttributes' => $this->configuration['backupStaticAttributes'] ?? false,
            'beStrictAboutChangesToGlobalState' => $this->configuration['beStrictAboutChangesToGlobalState'] ?? false,
            'bootstrap' => $this->configuration['bootstrap'] ?? 'vendor/autoload.php',
            'cacheResult' => true,
            'cacheResultFile' => '.phpunit.result.cache',
            'colors' => $this->configuration['colors'] ?? 'always',
            'columns' => 80,
            'convertDeprecationsToExceptions' => $this->configuration['convertDeprecationsToExceptions'] ?? false,
            'convertErrorsToExceptions' => $this->configuration['convertErrorsToExceptions'] ?? true,
            'convertNoticesToExceptions' => $this->configuration['convertNoticesToExceptions'] ?? true,
            'convertWarningsToExceptions' => $this->configuration['convertWarningsToExceptions'] ?? true,
            'processIsolation' => $this->configuration['processIsolation'] ?? false,
            'stopOnDefect' => false,
            'stopOnError' => false,
            'stopOnFailure' => $this->configuration['stopOnFailure'] ?? false,
            'stopOnWarning' => false,
            'stopOnIncomplete' => false,
            'stopOnRisky' => false,
            'stopOnSkipped' => false,
            'failOnEmptyTestSuite' => false,
            'failOnIncomplete' => false,
            'failOnRisky' => false,
            'failOnSkipped' => false,
            'failOnWarning' => false,
            'testSuiteLoaderFile' => false,
            'verbose' => $this->configuration['verbose'] ?? false,
            'timeoutForSmallTests' => 1,
            'timeoutForMediumTests' => 10,
            'timeoutForLargeTests' => 60,
        ];

        // Apply PHP ini settings
        if (isset($this->configuration['php']['ini'])) {
            foreach ($this->configuration['php']['ini'] as $key => $value) {
                ini_set($key, $value);
            }
        }

        return $config;
    }

    private function processTestResults($result, string $output): void
    {
        $this->testResults['passed'] = $result->count() - $result->failureCount() - $result->errorCount();
        $this->testResults['failed'] = $result->failureCount() + $result->errorCount();
    }

    private function reportFinalSummary(): void
    {
        $duration = round(microtime(true) - $this->startTime, 2);
        $this->testReporter->reportSummary(
            $this->testResults['passed'],
            $this->testResults['failed'],
            $duration
        );
    }

    public function getConfiguration(): array
    {
        return $this->configuration;
    }

    public function setConfiguration(array $configuration): void
    {
        $this->configuration = $configuration;
    }

    // For testing and dependency injection
    public function getTestSuite(): TestSuiteInterface
    {
        return $this->testSuite;
    }

    public function getLifecycleManager(): LifecycleManagerInterface
    {
        return $this->lifecycleManager;
    }

    public function getTestReporter(): TestReporter
    {
        return $this->testReporter;
    }
}
