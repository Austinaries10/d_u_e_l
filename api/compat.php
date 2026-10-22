<?php
// Some shared hosts disable php_uname() in disable_functions. The Anthropic SDK calls it (unqualified,
// inside the Anthropic\Core namespace) only to report the CPU architecture in a request header.
// PHP looks for a function in the current namespace first, so this stand-in is used when the real one is off.

namespace Anthropic\Core;

if (!\function_exists('php_uname') && !\function_exists(__NAMESPACE__ . '\php_uname')) {
    function php_uname(string $mode = 'a'): string
    {
        return match ($mode) {
            's' => \PHP_OS_FAMILY,
            'm' => 'unknown',
            default => \PHP_OS,
        };
    }
}
