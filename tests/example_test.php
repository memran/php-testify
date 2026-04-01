<?php

declare(strict_types=1);

use function Testify\describe;
use function Testify\expect;
use function Testify\it;

describe('Math basics', function () {

    it('adds numbers correctly', function () {
        $sum = 2 + 3;
        expect($sum)->toBe(5);
    });

    it('compares loosely', function () {
        $value = "10";
        expect($value)->toEqual(10); // == ok
    });

    it('compares strictly when values match', function () {
        $x = 100;
        expect($x)->toBe(100);
    });
});
