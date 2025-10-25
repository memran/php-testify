<?php

namespace Testify;

use PHPUnit\Framework\TestSuite as PHPUnitTestSuite;
use PHPUnit\TextUI\TestRunner as PHPUnitTestRunner;
use Testify\Core\{TestSuite, TestReporter, TestCaseFactory, LifecycleManager, ConfigurationManager};
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
        $configuration['printer'] = $this->testReporter;
        $runner = new PHPUnitTestRunner();
        $result = $runner->run($phpUnitSuite, $configuration);
        $this->lifecycleManager->executeAfterAll();

        exit($result->wasSuccessful() ? 0 : 1);
    }

    private function loadDefaultConfiguration(): array
    {
        $configFile = __DIR__ . '/../../phpunit.config.php';
        return (new ConfigurationManager($configFile))->getAll();
    }
    private function createPHPUnitConfiguration(): array
    {

        $config = [
            'extensions' => [],
            'loadedExtensions' => [],
            'notLoadedExtensions' => [],
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
