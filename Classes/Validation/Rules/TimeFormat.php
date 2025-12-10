<?php

declare(strict_types=1);

/*
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace QkSkima\Model\Validation\Rules;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::IS_REPEATABLE)]
class TimeFormat
{
    public function __construct(
        public string $format = 'H:i',
        public string $message = 'is not a valid time (expected format: {format})'
    ) {}

    public function isValid(string $value): bool
    {
        $time = \DateTime::createFromFormat($this->format, $value);

        // Ensure both valid parsing and exact match
        return $time && $time->format($this->format) === $value;
    }
}
