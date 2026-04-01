<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

use function Testify\afterEach;
use function Testify\beforeEach;
use function Testify\describe;
use function Testify\group;
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

    public function testNestedSuitesBuildATreeAndKeepTheirOwnMetadata(): void
    {
        describe('outer suite', static function (): void {
            group('feature', 'outer');

            it('outer test before nested suite', static function (): void {
            })->group('top-level');

            describe('inner suite', static function (): void {
                beforeEach(static function (): void {
                });

                it('inner test', static function (int $value): void {
                })->with([
                    'first' => [1],
                    'second' => [2],
                ])->group('inner');
            })->group('child-suite');

            afterEach(static function (): void {
            });

            it('outer test after nested suite', static function (): void {
            })->skip('not now');
        });

        $suites = TestSuite::getInstance()->all();

        self::assertCount(1, $suites);
        self::assertSame('outer suite', $suites[0]['name']);
        self::assertSame(['feature', 'outer'], $suites[0]['groups']);
        self::assertCount(2, $suites[0]['tests']);
        self::assertSame(['top-level'], $suites[0]['tests'][0]['groups']);
        self::assertSame('not now', $suites[0]['tests'][1]['skip']);
        self::assertCount(1, $suites[0]['children']);
        self::assertSame('inner suite', $suites[0]['children'][0]['name']);
        self::assertSame(['child-suite'], $suites[0]['children'][0]['groups']);
        self::assertCount(1, $suites[0]['children'][0]['beforeEach']);
        self::assertSame(['inner'], $suites[0]['children'][0]['tests'][0]['groups']);
        self::assertCount(2, $suites[0]['children'][0]['tests'][0]['datasets']);
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
