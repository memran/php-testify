<?php

use function Testify\describe;
use function Testify\it;
use function Testify\expect;
use function Testify\beforeAll;
use function Testify\afterAll;
use function Testify\beforeEach;
use function Testify\afterEach;

class DummyUser
{
    public string $name = 'Ada';
}

describe('php-testify expectation API', function () {
    // we'll mutate these in hooks to prove hooks work
    $shared = [
        'bootCount' => 0,
        'eachCount' => 0,
        'cleanup'   => [],
        'numbers'   => [],
    ];

    beforeAll(function () use (&$shared) {
        // runs once before all tests
        $shared['bootCount']++;
        $shared['numbers'] = [2, 4, 6];
    });

    afterAll(function () use (&$shared) {
        // runs once after all tests
        $shared['cleanup'][] = 'afterAll-called';
        // final assertion on lifecycle
        expect($shared['bootCount'])->toBe(1);
        expect($shared['cleanup'])->toContain('afterAll-called');
    });

    beforeEach(function () use (&$shared) {
        // runs before every it()
        $shared['eachCount']++;
        $shared['x'] = 10;
        $shared['y'] = 5;
        $shared['str'] = 'hello world';
        $shared['arr'] = ['alpha', 'beta', 'gamma'];
        $shared['user'] = new DummyUser();
        $shared['nullish'] = null;
    });

    afterEach(function () use (&$shared) {
        // runs after every it()
        // prove that something happened during test, then clean it
        if (isset($shared['dirty'])) {
            unset($shared['dirty']);
        }
    });

    it('toBe / toEqual basics', function () use (&$shared) {
        expect($shared['x'])->toBe(10);
        expect($shared['x'])->toEqual(10); // == is same in this case

        // Arrays: === would fail, but toEqual uses loose equality (==)
        $a = ['key' => 'value'];
        $b = ['key' => 'value'];
        expect($a)->toEqual($b);

        // sanity: strict equality vs loose (just to confirm not() also works)
        expect($a)->not()->toBe($b);
    });

    it('truthiness / falsiness / null', function () use (&$shared) {
        expect(true)->toBeTrue();
        expect(false)->toBeFalse();
        expect($shared['nullish'])->toBeNull();

        expect(1)->toBeTruthy();
        expect("nonempty")->toBeTruthy();
        expect(0)->toBeFalsy();
        expect("")->toBeFalsy();

        // negated versions
        expect($shared['nullish'])->not()->toBeTruthy();
        expect("")->not()->toBeTruthy();
        expect("x")->not()->toBeFalsy();
    });

    it('numeric comparisons', function () use (&$shared) {
        expect($shared['x'])->toBeGreaterThan($shared['y']); // 10 > 5
        expect($shared['y'])->toBeLessThan($shared['x']);   // 5 < 10

        // negated
        expect($shared['y'])->not()->toBeGreaterThan($shared['x']);
        expect($shared['x'])->not()->toBeLessThan($shared['y']);
    });

    it('containment and lengths', function () use (&$shared) {
        // strings
        expect($shared['str'])->toContain('hello');
        expect($shared['str'])->toContain('world');

        // arrays
        expect($shared['arr'])->toContain('alpha');
        expect($shared['arr'])->toContain('beta');

        // negated contain
        expect($shared['arr'])->not()->toContain('delta');
        expect($shared['str'])->not()->toContain('nope');

        // lengths
        expect($shared['str'])->toHaveLength(11); // "hello world" length 11
        expect($shared['arr'])->toHaveLength(3);

        // negated length
        expect($shared['arr'])->not()->toHaveLength(99);
        expect($shared['str'])->not()->toHaveLength(0);
    });

    it('instance and class checks', function () use (&$shared) {
        expect($shared['user'])->toBeInstanceOf(DummyUser::class);
        expect($shared['user'])->not()->toBeInstanceOf(\stdClass::class);
    });

    it('exception expectations with toThrow', function () {
        $willThrow = function () {
            throw new InvalidArgumentException("bad arg");
        };

        // should PASS
        expect($willThrow)->toThrow(InvalidArgumentException::class);

        // and negation should PASS because different exception
        $wontThrowThis = function () {
            throw new RuntimeException("other");
        };
        expect($wontThrowThis)->not()->toThrow(InvalidArgumentException::class);

        // also ensure something that throws anything matches default Throwable::class
        $anyThrow = function () {
            throw new \LogicException("xxx");
        };
        expect($anyThrow)->toThrow(\Throwable::class);
    });

    it('lifecycle hooks actually mutated shared state', function () use (&$shared) {
        // beforeAll ran once at entire suite start
        expect($shared['bootCount'])->toBe(1);

        // beforeEach increments eachCount for each test
        expect($shared['eachCount'])->toBeGreaterThan(0);

        // we can "dirty" something to prove afterEach cleans it next test
        $shared['dirty'] = 'temp-marker';

        // data from beforeAll should still exist
        expect($shared['numbers'])->toContain(2);
        expect($shared['numbers'])->toContain(6);
        expect($shared['numbers'])->not()->toContain(999);
    });

    it('afterEach cleaned previous dirty state', function () use (&$shared) {
        // If afterEach ran after last test, "dirty" should be gone now.
        // We simulate expectation with negated truthy.
        $isDirtyPresent = array_key_exists('dirty', $shared);
        expect($isDirtyPresent)->toBeFalse();
        expect($isDirtyPresent)->not()->toBeTrue();
    });
});
