<?php

declare(strict_types=1);

namespace Brick\ORM\Tests;

use Brick\ORM\TableAliasGenerator;

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for class TableAliasGenerator.
 */
class TableAliasGeneratorTest extends TestCase
{
    public function testGenerate()
    {
        $generator = new TableAliasGenerator();

        $expected = [
            'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm',
            'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z1',
            'z2', 'z3', 'z4', 'z5', 'z6', 'z7', 'z8', 'z9', 'z10', 'z11', 'z12'
        ];

        foreach ($expected as $value) {
            self::assertSame($value, $generator->generate());
        }
    }
}
