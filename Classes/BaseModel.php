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

namespace QkSkima\Model;

use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use QkSkima\Model\Validation\BusinessRuleInterface;

abstract class BaseModel
{
    protected const TABLE = '';
    protected const PRIMARY_KEY = 'uid';
    
    protected ?int $uid = null;
    protected ?int $crdate = null;
    protected ?int $tstamp = null;
    
    protected array $errors = [];
    protected array $originalData = [];
    protected bool $isNew = true;
    
    protected static ?ValidatorInterface $validator = null;
    protected static ?PropertyAccessor $propertyAccessor = null;
    
    /**
     * Factory method to create instance from array data
     */
    public static function fromArray(array $data): static
    {
        $instance = new static();
        $instance->hydrate($data);
        return $instance;
    }
    
    /**
     * Static create method (ActiveRecord style)
     */
    public static function create(array $data): static
    {
        $instance = static::fromArray($data);
        if ($instance->validate()) {
            $instance->save();
        }
        return $instance;
    }
    
    /**
     * Hydrate model from array data with nested relations support
     */
    public function hydrate(array $data): void
    {
        $accessor = $this->getPropertyAccessor();
        $relations = $this->getRelations();
        
        foreach ($data as $key => $value) {
            // Check if this is a relation
            if (isset($relations[$key])) {
                $relationClass = $relations[$key]['class'];
                $relationType = $relations[$key]['type'] ?? 'one'; // 'one' or 'many'
                
                if ($relationType === 'many' && is_array($value)) {
                    $relationInstances = [];
                    foreach ($value as $relationData) {
                        if (is_array($relationData)) {
                            $relationInstances[] = $relationClass::fromArray($relationData);
                        }
                    }
                    $this->setProperty($key, $relationInstances);
                } elseif ($relationType === 'one' && is_array($value)) {
                    $this->setProperty($key, $relationClass::fromArray($value));
                }
            } else {
                // Regular property
                $this->setProperty($key, $value);
            }
        }
        
        // Store original data for change tracking
        $this->originalData = $data;
        
        // Check if this is an existing record
        if (isset($data[static::PRIMARY_KEY]) && $data[static::PRIMARY_KEY] > 0) {
            $this->isNew = false;
        }
    }
    
    /**
     * Validate the model including nested relations and business rules
     */
    public function validate(): bool
    {
        $this->errors = [];
        
        // 1. Syntactic validation (Symfony constraints)
        $validator = $this->getValidator();
        $violations = $validator->validate($this);
        
        $this->processViolations($violations);
        
        // 2. Validate nested relations
        $this->validateRelations();
        
        // 3. Business rule validation (only if syntactic validation passed)
        if (empty($this->errors)) {
            $this->validateBusinessRules();
        }
        
        return empty($this->errors);
    }
    
    /**
     * Validate nested relations
     */
    protected function validateRelations(): void
    {
        $relations = $this->getRelations();
        $accessor = $this->getPropertyAccessor();
        
        foreach ($relations as $property => $config) {
            if ($accessor->isReadable($this, $property)) {
                $value = $accessor->getValue($this, $property);
                
                if (is_array($value)) {
                    foreach ($value as $index => $item) {
                        if ($item instanceof BaseModel) {
                            if (!$item->validate()) {
                                foreach ($item->getErrors() as $field => $messages) {
                                    $this->errors["{$property}.{$index}.{$field}"] = $messages;
                                }
                            }
                        }
                    }
                } elseif ($value instanceof BaseModel) {
                    if (!$value->validate()) {
                        foreach ($value->getErrors() as $field => $messages) {
                            $this->errors["{$property}.{$field}"] = $messages;
                        }
                    }
                }
            }
        }
    }
    
    /**
     * Validate business rules
     */
    protected function validateBusinessRules(): void
    {
        $businessRules = $this->getBusinessRules();
        
        foreach ($businessRules as $rule) {
            if (!$rule instanceof BusinessRuleInterface) {
                throw new \InvalidArgumentException(
                    'Business rule must implement BusinessRuleInterface'
                );
            }
            
            $result = $rule->validate($this);
            if (!$result->isValid()) {
                foreach ($result->getViolations() as $field => $messages) {
                    if (!isset($this->errors[$field])) {
                        $this->errors[$field] = [];
                    }
                    $this->errors[$field] = array_merge($this->errors[$field], $messages);
                }
            }
        }
    }
    
    /**
     * Process Symfony validation violations
     */
    protected function processViolations(ConstraintViolationListInterface $violations): void
    {
        foreach ($violations as $violation) {
            $property = $violation->getPropertyPath();
            if (!isset($this->errors[$property])) {
                $this->errors[$property] = [];
            }
            $this->errors[$property][] = $violation->getMessage();
        }
    }
    
    /**
     * Save the model (insert or update)
     */
    public function save(): bool
    {
        if (!$this->validate()) {
            return false;
        }
        
        if ($this->isNew) {
            return $this->insert();
        } else {
            return $this->update($this->toArray());
        }
    }
    
    /**
     * Insert new record
     */
    protected function insert(): bool
    {
        $connection = $this->getConnection();
        $data = $this->toArray(true);
        
        // Add timestamps
        $data['crdate'] = $data['tstamp'] = time();
        
        // Remove primary key for insert
        unset($data[static::PRIMARY_KEY]);
        
        // Remove relations from data
        $data = $this->stripRelations($data);
        
        try {
            $connection->insert(static::TABLE, $data);
            $this->uid = (int)$connection->lastInsertId(static::TABLE);
            $this->isNew = false;
            $this->crdate = $data['crdate'];
            $this->tstamp = $data['tstamp'];
            
            // Save relations if cascade is enabled
            $this->saveRelations();
            
            return true;
        } catch (\Exception $e) {
            $this->errors['_database'] = [$e->getMessage()];
            return false;
        }
    }
    
    /**
     * Update existing record
     */
    public function update(array $data): bool
    {
        if ($this->isNew) {
            throw new \RuntimeException('Cannot update a new record. Use save() instead.');
        }
        
        // Merge new data
        $this->hydrate(array_merge($this->toArray(), $data));
        
        if (!$this->validate()) {
            return false;
        }
        
        $connection = $this->getConnection();
        $updateData = $this->toArray(true);
        
        // Update timestamp
        $updateData['tstamp'] = time();
        
        // Remove primary key and relations
        unset($updateData[static::PRIMARY_KEY]);
        $updateData = $this->stripRelations($updateData);
        
        try {
            $connection->update(
                static::TABLE,
                $updateData,
                [static::PRIMARY_KEY => $this->uid]
            );
            
            $this->tstamp = $updateData['tstamp'];
            
            // Update relations
            $this->saveRelations();
            
            return true;
        } catch (\Exception $e) {
            $this->errors['_database'] = [$e->getMessage()];
            return false;
        }
    }
    
    /**
     * Delete the record
     */
    public function destroy(): bool
    {
        if ($this->isNew) {
            throw new \RuntimeException('Cannot delete a new record.');
        }
        
        $connection = $this->getConnection();
        
        try {
            // Delete relations first
            $this->destroyRelations();
            
            $connection->delete(
                static::TABLE,
                [static::PRIMARY_KEY => $this->uid]
            );
            
            return true;
        } catch (\Exception $e) {
            $this->errors['_database'] = [$e->getMessage()];
            return false;
        }
    }
    
    /**
     * Save related models (override in subclass for custom behavior)
     */
    protected function saveRelations(): void
    {
        // Override in subclass if needed
    }
    
    /**
     * Destroy related models (override in subclass for custom behavior)
     */
    protected function destroyRelations(): void
    {
        // Override in subclass if needed
    }
    
    /**
     * Convert model to array
     */
    public function toArray(bool $includeRelations = false): array
    {
        $data = [];
        $accessor = $this->getPropertyAccessor();
        $reflection = new \ReflectionClass($this);
        
        foreach ($reflection->getProperties() as $property) {
            $propertyName = $property->getName();
            
            // Skip protected/private base model properties
            if (in_array($propertyName, ['errors', 'originalData', 'isNew', 'validator', 'propertyAccessor'])) {
                continue;
            }
            
            if ($accessor->isReadable($this, $propertyName)) {
                $value = $accessor->getValue($this, $propertyName);
                
                if ($value instanceof BaseModel && $includeRelations) {
                    $data[$propertyName] = $value->toArray($includeRelations);
                } elseif (is_array($value) && $includeRelations) {
                    $data[$propertyName] = array_map(function($item) use ($includeRelations) {
                        return $item instanceof BaseModel ? $item->toArray($includeRelations) : $item;
                    }, $value);
                } elseif (!($value instanceof BaseModel) && !is_array($value)) {
                    $data[$propertyName] = $value;
                }
            }
        }
        
        return $data;
    }
    
    /**
     * Strip relations from data array
     */
    protected function stripRelations(array $data): array
    {
        $relations = $this->getRelations();
        foreach (array_keys($relations) as $relationKey) {
            unset($data[$relationKey]);
        }
        return $data;
    }
    
    /**
     * Get validation errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
    
    /**
     * Check if model has errors
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }
    
    /**
     * Set a property value
     */
    protected function setProperty(string $property, mixed $value): void
    {
        $accessor = $this->getPropertyAccessor();
        if ($accessor->isWritable($this, $property)) {
            $accessor->setValue($this, $property, $value);
        }
    }
    
    /**
     * Get property value
     */
    protected function getProperty(string $property): mixed
    {
        $accessor = $this->getPropertyAccessor();
        if ($accessor->isReadable($this, $property)) {
            return $accessor->getValue($this, $property);
        }
        return null;
    }
    
    /**
     * Define relations (override in subclass)
     * Format: ['propertyName' => ['class' => ClassName::class, 'type' => 'one|many']]
     */
    protected function getRelations(): array
    {
        return [];
    }
    
    /**
     * Define business rules (override in subclass)
     * Return array of BusinessRuleInterface instances
     */
    protected function getBusinessRules(): array
    {
        return [];
    }
    
    /**
     * Get Symfony validator instance
     */
    protected function getValidator(): ValidatorInterface
    {
        if (self::$validator === null) {
            self::$validator = Validation::createValidatorBuilder()
                ->enableAttributeMapping()
                ->getValidator();
        }
        return self::$validator;
    }
    
    /**
     * Get property accessor instance
     */
    protected function getPropertyAccessor(): PropertyAccessor
    {
        if (self::$propertyAccessor === null) {
            self::$propertyAccessor = PropertyAccess::createPropertyAccessor();
        }
        return self::$propertyAccessor;
    }
    
    /**
     * Get database connection
     */
    protected function getConnection(): Connection
    {
        return GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable(static::TABLE);
    }
    
    /**
     * Get query builder
     */
    protected function getQueryBuilder()
    {
        return GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable(static::TABLE);
    }
}