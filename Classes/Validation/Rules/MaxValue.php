<?php

declare(strict_types=1);

/*
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace QkSkima\Model\Validation\Rules;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::IS_REPEATABLE)]
class MaxValue
{
    public function __construct(
        public int|float $max,
        public string $message = 'must be less than or equal to {max}'
    ) {}
}
