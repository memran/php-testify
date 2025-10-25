<?php

namespace Testify;

/**
 * Custom exception for assertion failures inside Expect.
 * Keeping it separate helps us in Printer to detect "assertion fail"
 * vs "test crashed with runtime error".
 */
final class TestFailureException extends \Exception {}
