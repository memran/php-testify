<?php

use PHPUnit\Framework\TestCase;

final class SampleTest extends TestCase
{
    public function test_adds_numbers(): void
    {
        $sum = 2 + 3;
        $this->assertSame(5, $sum);
    }

    public function test_fails_on_purpose(): void
    {
        $x = 100;
        $this->assertSame(200, $x, "x should be 200");
    }

    public function test_skipped_example(): void
    {
        $this->markTestSkipped('feature disabled');
    }
}
