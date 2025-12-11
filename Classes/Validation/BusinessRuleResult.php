<?php declare(strict_types=1);

#
# MIT License
#
# Copyright (c) 2024 Colin Atkins
#
# Permission is hereby granted, free of charge, to any person obtaining a copy
# of this software and associated documentation files (the "Software"), to deal
# in the Software without restriction, including without limitation the rights
# to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
# copies of the Software, and to permit persons to whom the Software is
# furnished to do so, subject to the following conditions:
#
# The above copyright notice and this permission notice shall be included in all
# copies or substantial portions of the Software.
#
# THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
# IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
# FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
# AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
# LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
# OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
# SOFTWARE.
#

namespace QkSkima\Model\Validation;

class BusinessRuleResult
{
    protected bool $valid = true;
    protected array $violations = [];
    
    /**
     * Create a successful validation result
     */
    public static function success(): self
    {
        return new self();
    }
    
    /**
     * Create a failed validation result
     */
    public static function failure(): self
    {
        $result = new self();
        $result->valid = false;
        return $result;
    }
    
    /**
     * Add a violation for a specific field
     * 
     * @param string $field The field path (e.g., 'start_date' or 'order_items.0.start_date')
     * @param string $message The error message
     */
    public function addViolation(string $field, string $message): self
    {
        $this->valid = false;
        
        if (!isset($this->violations[$field])) {
            $this->violations[$field] = [];
        }
        
        $this->violations[$field][] = $message;
        
        return $this;
    }
    
    /**
     * Check if the validation passed
     */
    public function isValid(): bool
    {
        return $this->valid;
    }
    
    /**
     * Get all violations
     * Returns array with field paths as keys and arrays of error messages as values
     */
    public function getViolations(): array
    {
        return $this->violations;
    }
    
    /**
     * Get violations for a specific field
     */
    public function getViolationsForField(string $field): array
    {
        return $this->violations[$field] ?? [];
    }
    
    /**
     * Merge another result into this one
     */
    public function merge(BusinessRuleResult $other): self
    {
        if (!$other->isValid()) {
            $this->valid = false;
            foreach ($other->getViolations() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->addViolation($field, $message);
                }
            }
        }
        return $this;
    }
}