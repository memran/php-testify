<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class SampleTest extends TestCase
{
    public function test_adds_numbers(): void
    {
        $sum = 2 + 3;
        $this->assertSame(5, $sum);
    }

    public function test_supports_simple_assertions(): void
    {
        $x = 100;
        $this->assertSame(100, $x);
    }

    public function test_skipped_example(): void
    {
        $this->markTestSkipped('feature disabled');
    }
}
