<?php

declare(strict_types=1);

namespace Brick\ORM\Exception;

use Exception;

use function bin2hex;
use function count;
use function implode;
use function is_string;
use function preg_replace_callback;
use function strtoupper;

/**
 * Base class for all ORM exceptions.
 */
class ORMException extends Exception
{
    /**
     * Returns a parsable string representation of the given string, hex-encoding every non-printable ASCII char.
     *
     * Example: "ABC\xFE\xFF"
     */
    protected static function exportString(string $string): string
    {
        $export = preg_replace_callback('/[^\x20-\x7E]/', function (array $matches) {
            /** @var array{string} $matches */
            return '\x' . strtoupper(bin2hex($matches[0]));
        }, $string);

        return '"' . $export . '"';
    }

    /**
     * Returns a parsable string representation of the given array of scalars (int|string).
     *
     * This method is used to produce useful exception messages containing object identities.
     *
     * Examples:
     *
     * - 123
     * - [123, "ABC"]
     *
     * @param list<int|string> $scalarIdentity The identity, as a list of scalar values. Must contain at least one entry.
     *                                         Each entry must be an int or a string.
     */
    protected static function exportScalarIdentity(array $scalarIdentity): string
    {
        $result = [];

        foreach ($scalarIdentity as $value) {
            if (is_string($value)) {
                $result[] = self::exportString($value);
            } else {
                $result[] = (string) $value;
            }
        }

        $result = implode(', ', $result);

        if (count($scalarIdentity) === 1) {
            return $result;
        }

        return '[' . $result . ']';
    }
}
