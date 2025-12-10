<?php

declare(strict_types=1);

/*
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace QkSkima\Model;

use QkSkima\Model\Validation\Errors;
use QkSkima\Model\Validation\Guards\GuardInterface;
use QkSkima\Model\Validation\Validator;
use ReflectionClass;
use TYPO3\CMS\Core\Utility\GeneralUtility;

abstract class BaseModel
{
    /** @var Errors|null */
    public ?Errors $errors = null;

    /** @var GuardInterface[] */
    protected array $guards = [];

    /**
     * Add a business rule guard declaratively
     */
    public function addGuard(GuardInterface $guard): void
    {
        $this->guards[] = $guard;
    }

    public function validate(): bool
    {
        // Standard syntactic validations
        $this->errors = Validator::validate($this);

        // Before running guards. All syntatic errors must not exist
        if ($this->errors->any())
            return false;

        // Run all guards
        foreach ($this->guards as $guard) {
            $guardErrors = $guard->validate($this);
            foreach ($guardErrors->all() as $field => $messages) {
                foreach ($messages as $msg) {
                    $this->errors->add($field, $msg['message'], $msg['context'] ?? []);
                }
            }
        }

        return !$this->errors->any();
    }

    public function create(): bool
    {
        if (!$this->validate()) {
            return false;
        }

        // Dynamically resolve repository
        $repository = $this->resolveRepository();

        if (!$repository || !method_exists($repository, 'create')) {
            throw new \RuntimeException(sprintf(
                'Repository for model %s not found or missing create() method',
                static::class
            ));
        }

        return $repository->create($this);
    }

    protected function resolveRepository(string $modelClass = ''): ?object
    {
        // Autoresolve
        if (empty($modelClass)) {
            $modelClass = static::class;
        }
        $reflection = new ReflectionClass($modelClass);

        // Example: Vendor\MyExtension\Domain\Model\Booking
        // → Vendor\MyExtension\Repository\BookingRepository
        $namespaceParts = explode('\\', $reflection->getNamespaceName());
        array_pop($namespaceParts); // remove 'Model'
        $namespaceParts[] = 'Repository';
        $shortName = $reflection->getShortName();
        $repositoryClass = implode('\\', $namespaceParts) . '\\' . $shortName . 'Repository';

        if (class_exists($repositoryClass)) {
            return GeneralUtility::makeInstance($repositoryClass);
        }

        return null;
    }
}
