<?php

declare(strict_types=1);

/*
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace QkSkima\Model\Validation;

use QkSkima\Model\Validation\Guards\GuardInterface;
use ReflectionObject;
use QkSkima\Model\Validation\Rules\{
    Presence,
    MinLength,
    ConditionalPresence,
    MinValue,
    MaxValue,
    Email,
    SameAs,
    DateFormat,
    TimeFormat
};

class Validator
{
    public static function validate(object $model): Errors
    {
        $errors = new Errors();
        $reflection = new ReflectionObject($model);

        foreach ($reflection->getProperties() as $property) {
            // SKIP protected and private properties (like guards, errors from BaseModel)
            if (!$property->isPublic()) {
                continue;
            }
            
            // Get value - use default value if not initialized
            if ($property->isInitialized($model)) {
                $value = $property->getValue($model);
            } else {
                // For uninitialized properties, check if there's a default value
                $value = $property->hasDefaultValue() ? $property->getDefaultValue() : null;
            }
            
            $propertyName = $property->getName();
            
            // SKIP validation of Errors objects to prevent recursion
            if ($value instanceof Errors) {
                continue;
            }
            
            // SKIP validation of guard objects (they're infrastructure, not data)
            if ($propertyName === 'guards' || $value instanceof GuardInterface) {
                continue;
            }
            
            // Handle nested objects
            if (is_object($value) && !($value instanceof \DateTime)) {
                $nestedErrors = self::validate($value);
                $allErrors = $nestedErrors->all();
                if (!empty($allErrors)) {
                    foreach ($allErrors as $field => $messages) {
                        foreach ($messages as $errorData) {
                            // Extract message and context from the error data array
                            $errors->add(
                                "{$propertyName}.{$field}", 
                                $errorData['message'],
                                $errorData['context'] ?? []
                            );
                        }
                    }
                }
                continue;
            }
            
            // Handle arrays of objects
            if (is_array($value)) {
                foreach ($value as $index => $item) {
                    if (is_object($item) && !($item instanceof \DateTime)) {
                        $nestedErrors = self::validate($item);
                        $allErrors = $nestedErrors->all();
                        if (!empty($allErrors)) {
                            foreach ($allErrors as $field => $messages) {
                                foreach ($messages as $errorData) {
                                    // Use itemId if available, otherwise use array index
                                    $itemKey = property_exists($item, 'itemId') && isset($item->itemId) 
                                        ? $item->itemId 
                                        : $index;
                                    // Extract message and context from the error data array
                                    $errors->add(
                                        "{$propertyName}.{$itemKey}.{$field}", 
                                        $errorData['message'],
                                        $errorData['context'] ?? []
                                    );
                                }
                            }
                        }
                    }
                }
                continue;
            }

            $attributes = $property->getAttributes();

            foreach ($attributes as $attr) {
                $instance = $attr->newInstance();

                // === Presence ===
                if ($instance instanceof Presence) {
                    // Check for empty values including empty strings
                    $isEmpty = $value === null || $value === '' || $value === [] || $value === false;
                    if ($isEmpty) {
                        $errors->add($propertyName, $instance->message);
                    }
                }

                // === MinLength ===
                if ($instance instanceof MinLength && is_string($value) && strlen($value) < $instance->min) {
                    $errors->add(
                        $propertyName,
                        str_replace('{min}', $instance->min, $instance->message),
                        ['min' => $instance->min]
                    );
                }

                // === ConditionalPresence ===
                if ($instance instanceof ConditionalPresence) {
                    $ifFieldProperty = $reflection->getProperty($instance->ifField);
                    $ifFieldValue = $ifFieldProperty->isInitialized($model) 
                        ? $ifFieldProperty->getValue($model) 
                        : ($ifFieldProperty->hasDefaultValue() ? $ifFieldProperty->getDefaultValue() : null);
                    
                    if ($ifFieldValue === $instance->ifValue) {
                        $isEmpty = $value === null || $value === '' || $value === [] || $value === false;
                        if ($isEmpty) {
                            $errors->add(
                                $propertyName,
                                str_replace(
                                    ['{ifField}', '{ifValue}'],
                                    [$instance->ifField, $instance->ifValue],
                                    $instance->message
                                ),
                                ['ifField' => $instance->ifField, 'ifValue' => $instance->ifValue]
                            );
                        }
                    }
                }

                // === MinValue ===
                if ($instance instanceof MinValue && $value !== null && $value < $instance->min) {
                    $errors->add(
                        $propertyName,
                        str_replace('{min}', $instance->min, $instance->message),
                        ['min' => $instance->min, 'actual' => $value]
                    );
                }

                // === MaxValue ===
                if ($instance instanceof MaxValue && $value !== null && $value > $instance->max) {
                    $errors->add(
                        $propertyName,
                        str_replace('{max}', $instance->max, $instance->message),
                        ['max' => $instance->max, 'actual' => $value]
                    );
                }

                // === Email ===
                if ($instance instanceof Email && !empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors->add($propertyName, $instance->message);
                }

                // === SameAs ===
                if ($instance instanceof SameAs) {
                    $compareProperty = $reflection->getProperty($instance->name);
                    $compareValue = $compareProperty->isInitialized($model) 
                        ? $compareProperty->getValue($model) 
                        : ($compareProperty->hasDefaultValue() ? $compareProperty->getDefaultValue() : null);

                    if ($value !== $compareValue) {
                        $errors->add(
                            $propertyName,
                            str_replace('{name}', $instance->name, $instance->message),
                            ['expectedField' => $instance->name]
                        );
                    }
                }

                // === DateFormat ===
                if ($instance instanceof DateFormat) {
                    // Skip validation if value is null or empty string (use Presence for required validation)
                    if ($value !== null && $value !== '') {
                        if (!is_string($value) || !$instance->isValid($value)) {
                            $errors->add(
                                $propertyName,
                                str_replace('{format}', $instance->format, $instance->message),
                                ['format' => $instance->format, 'value' => $value]
                            );
                        }
                    }
                }

                // === TimeFormat ===
                if ($instance instanceof TimeFormat) {
                    // Skip validation if value is null or empty string (use Presence for required validation)
                    if ($value !== null && $value !== '') {
                        if (!is_string($value) || !$instance->isValid($value)) {
                            $errors->add(
                                $propertyName,
                                str_replace('{format}', $instance->format, $instance->message),
                                ['format' => $instance->format, 'value' => $value]
                            );
                        }
                    }
                }
            }
        }

        return $errors;
    }
}