<?php

use function Testify\describe;
use function Testify\it;
use function Testify\expect;

describe('Math basics', function () {

    it('adds numbers correctly', function () {
        $sum = 2 + 3;
        expect($sum)->toBe(5);
    });

    it('compares loosely', function () {
        $value = "10";
        expect($value)->toEqual(10); // == ok
    });

    it('fails on purpose (to see printer)', function () {
        $x = 100;
        expect($x)->toBe(200, 'x should be 200');
    });
});
