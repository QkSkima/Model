<?php

declare(strict_types=1);

/*
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace QkSkima\Model\Validation\Rules;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::IS_REPEATABLE)]
class DateFormat
{
    public function __construct(
        public string $format = 'Y-m-d',
        public string $message = 'is not a valid date (expected format: {format})'
    ) {}

    public function isValid(string $value): bool
    {
        $date = \DateTime::createFromFormat($this->format, $value);
        return $date && $date->format($this->format) === $value;
    }
}
