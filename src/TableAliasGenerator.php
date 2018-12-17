<?php

declare(strict_types=1);

namespace Brick\ORM;

class TableAliasGenerator
{
    /**
     * @var int
     */
    private $number = 0;

    /**
     * @return string
     */
    public function generate() : string
    {
        $this->number++;

        if ($this->number <= 25) {
            // a to y
            return chr(96 + $this->number);
        }

        // z1, z2, etc.
        return 'z' . ($this->number - 25);
    }
}
