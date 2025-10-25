<?php

namespace Testify;

function describe(string $description, callable $callback): void
{
    PHPTestify::getInstance()->describe($description, $callback);
    // Report suite completion after describe block
    // PHPTestify::getInstance()->getTestReporter()->reportSuiteCompletion();
}

function test(string $description, callable $callback): void
{
    PHPTestify::getInstance()->test($description, $callback);
}

function it(string $description, callable $callback): void
{
    PHPTestify::getInstance()->it($description, $callback);
}

function beforeEach(callable $callback): void
{
    PHPTestify::getInstance()->beforeEach($callback);
}

function afterEach(callable $callback): void
{
    PHPTestify::getInstance()->afterEach($callback);
}

function beforeAll(callable $callback): void
{
    PHPTestify::getInstance()->beforeAll($callback);
}

function afterAll(callable $callback): void
{
    PHPTestify::getInstance()->afterAll($callback);
}

function expect($value): Expectation
{
    return PHPTestify::getInstance()->expect($value);
}

function runTests(): void
{
    PHPTestify::getInstance()->run();
}
