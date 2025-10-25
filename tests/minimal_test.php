<?php

use function Testify\describe;
use function Testify\it;
use function Testify\expect;
use function Testify\beforeAll;
use function Testify\afterAll;
use function Testify\beforeEach;
use function Testify\afterEach;

describe('Database test', function () {
    $shared = [];

    beforeAll(function () use (&$shared) {
        $shared['db'] = 'connected';
    });

    afterAll(function () use (&$shared) {
        $shared['db'] = 'disconnected';
    });

    beforeEach(function () use (&$shared) {
        $shared['x'] = 2;
        $shared['y'] = 3;
    });

    afterEach(function () use (&$shared) {
        unset($shared['temp']);
    });

    it('Before all value', function () use (&$shared) {
        expect($shared['db'])->toBe('connected');
    });
    it('adds numbers', function () use (&$shared) {
        $sum = $shared['x'] + $shared['y'];
        expect($sum)->toBe(5);
        $shared['temp'] = 'touched';
    });

    it('multiplies numbers', function () use (&$shared) {
        $prod = $shared['x'] * $shared['y'];
        expect($prod)->toBe(6);
    });
});
