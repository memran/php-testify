<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

use function Testify\afterEach;
use function Testify\beforeEach;
use function Testify\describe;
use function Testify\it;

use Testify\TestSuite;

final class TestSuiteTest extends TestCase
{
    protected function setUp(): void
    {
        TestSuite::getInstance()->reset();
    }

    protected function tearDown(): void
    {
        TestSuite::getInstance()->reset();
    }

    public function testNestedSuitesKeepRegisteringTestsAgainstTheActiveSuite(): void
    {
        describe('outer suite', static function (): void {
            it('outer test before nested suite', static function (): void {
            });

            describe('inner suite', static function (): void {
                it('inner test', static function (): void {
                });
            });

            beforeEach(static function (): void {
            });

            afterEach(static function (): void {
            });

            it('outer test after nested suite', static function (): void {
            });
        });

        $suites = TestSuite::getInstance()->all();

        self::assertCount(2, $suites);
        self::assertSame('outer suite', $suites[0]['name']);
        self::assertCount(2, $suites[0]['tests']);
        self::assertSame('outer test before nested suite', $suites[0]['tests'][0]['name']);
        self::assertSame('outer test after nested suite', $suites[0]['tests'][1]['name']);
        self::assertCount(1, $suites[0]['beforeEach']);
        self::assertCount(1, $suites[0]['afterEach']);
        self::assertSame('inner suite', $suites[1]['name']);
        self::assertCount(1, $suites[1]['tests']);
    }

    public function testHooksOutsideDescribeThrowHelpfulException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No active describe() context.');

        beforeEach(static function (): void {
        });
    }

    public function testResetClearsRegisteredSuites(): void
    {
        describe('temporary suite', static function (): void {
            it('temporary test', static function (): void {
            });
        });

        TestSuite::getInstance()->reset();

        self::assertSame([], TestSuite::getInstance()->all());
    }
}
