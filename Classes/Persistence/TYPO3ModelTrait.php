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

namespace QkSkima\Model\Persistence;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Trait providing TYPO3-specific functionality
 * Use this trait in models that need soft delete, hidden fields, etc.
 */
trait TYPO3ModelTrait
{
    public ?int $deleted = 0;
    public ?int $hidden = 0;
    public ?int $starttime = 0;
    public ?int $endtime = 0;
    public ?int $sys_language_uid = 0;
    public ?int $l10n_parent = 0;
    
    /**
     * Soft delete - marks record as deleted instead of removing it
     */
    public function softDelete(): bool
    {
        if ($this->isNew) {
            throw new \RuntimeException('Cannot delete a new record.');
        }
        
        $connection = $this->getConnection();
        
        try {
            $connection->update(
                static::TABLE,
                ['deleted' => 1, 'tstamp' => time()],
                [static::PRIMARY_KEY => $this->uid]
            );
            
            $this->deleted = 1;
            return true;
        } catch (\Exception $e) {
            $this->errors['_database'] = [$e->getMessage()];
            return false;
        }
    }
    
    /**
     * Find all non-deleted records
     */
    public static function findAll(int $limit = 100, int $offset = 0): array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable(static::TABLE);
        
        $queryBuilder->getRestrictions()->removeAll();
        
        $rows = $queryBuilder
            ->select('*')
            ->from(static::TABLE)
            ->where(
                $queryBuilder->expr()->eq('deleted', 0)
            )
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->executeQuery()
            ->fetchAllAssociative();
        
        return array_map(fn($row) => static::fromArray($row), $rows);
    }
    
    /**
     * Find by multiple criteria
     */
    public static function findBy(array $criteria, int $limit = 100): array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable(static::TABLE);
        
        $queryBuilder->getRestrictions()->removeAll();
        $queryBuilder->select('*')->from(static::TABLE);
        
        // Add deleted check
        $queryBuilder->where(
            $queryBuilder->expr()->eq('deleted', 0)
        );
        
        // Add criteria
        foreach ($criteria as $field => $value) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->eq(
                    $field,
                    $queryBuilder->createNamedParameter($value)
                )
            );
        }
        
        $rows = $queryBuilder
            ->setMaxResults($limit)
            ->executeQuery()
            ->fetchAllAssociative();
        
        return array_map(fn($row) => static::fromArray($row), $rows);
    }
    
    /**
     * Find one by criteria
     */
    public static function findOneBy(array $criteria): ?static
    {
        $results = static::findBy($criteria, 1);
        return $results[0] ?? null;
    }
    
    /**
     * Check if record is visible (not hidden, within time constraints)
     */
    public function isVisible(): bool
    {
        if ($this->hidden === 1) {
            return false;
        }
        
        $now = time();
        
        if ($this->starttime > 0 && $this->starttime > $now) {
            return false;
        }
        
        if ($this->endtime > 0 && $this->endtime < $now) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Hide the record
     */
    public function hide(): bool
    {
        $this->hidden = 1;
        return $this->save();
    }
    
    /**
     * Show the record
     */
    public function show(): bool
    {
        $this->hidden = 0;
        return $this->save();
    }
    
    /**
     * Set visibility time constraints
     */
    public function setVisibilityPeriod(?\DateTime $start = null, ?\DateTime $end = null): void
    {
        $this->starttime = $start ? $start->getTimestamp() : 0;
        $this->endtime = $end ? $end->getTimestamp() : 0;
    }
}