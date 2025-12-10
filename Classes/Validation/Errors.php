<?php

declare(strict_types=1);

/*
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace QkSkima\Model\Validation;

class Errors implements \ArrayAccess, \IteratorAggregate, \Countable
{
    private array $errors = [];

    public function add(string $attribute, string $message, array $context = []): void
    {
        $this->errors[$attribute][] = [
            'message' => $message,
            'context' => $context
        ];
    }

    public function for(string $attribute): array
    {
        return $this->errors[$attribute] ?? [];
    }

    public function any(): bool
    {
        return !empty($this->errors);
    }

    public function all(): array
    {
        return $this->errors;
    }

    // Countable interface
    public function count(): int
    {
        // Count total messages (flattened)
        $count = 0;
        foreach ($this->errors as $messages) {
            $count += count($messages);
        }
        return $count;
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->errors);
    }

    public function offsetExists($offset): bool { return isset($this->errors[$offset]); }
    public function offsetGet($offset): mixed { return $this->errors[$offset] ?? null; }
    public function offsetSet($offset, $value): void { $this->errors[$offset] = $value; }
    public function offsetUnset($offset): void { unset($this->errors[$offset]); }
}
