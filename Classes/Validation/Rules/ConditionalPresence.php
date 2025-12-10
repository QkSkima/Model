<?php

declare(strict_types=1);

/*
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace QkSkima\Model\Validation\Rules;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::IS_REPEATABLE)]
class ConditionalPresence
{
    public function __construct(
        public string $ifField,
        public mixed $ifValue,
        public string $message = 'must be present because {ifField} is {ifValue}'
    ) {}
}
