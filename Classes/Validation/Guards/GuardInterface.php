<?php

declare(strict_types=1);

/*
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace QkSkima\Model\Validation\Guards;

use QkSkima\Model\Validation\Errors;

interface GuardInterface
{
    /**
     * Validates the given model instance.
     * Returns an Errors object with violations if any.
     */
    public function validate(object $model): Errors;
}
