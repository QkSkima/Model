# QkSkima Model

A lean but powerful DSL-based model system for TYPO3 with Symfony validation, property access, nested relations, and business rule validation. QkSkima Model is an alternative approach, when you think Extbase is too heavy for the job.

## Features

- **ActiveRecord-style CRUD** operations (create, save, update, destroy)
- **Automatic hydration** from form data with nested relations
- **Symfony validation** with attribute-based constraints
- **Business rule validation** layer for complex domain logic
- **Nested model support** with automatic validation propagation
- **TYPO3 QueryBuilder** integration
- **Comprehensive error handling** with structured error messages
- **TYPO3 specific database operations** like softDelete, hide, show, findAll

## Recommendations

For building RESTful API endpoints that work seamlessly with this model system, we highly recommend using the **`qkskima/api`** composer package.

### Why Use QkSkima Api?

The `qkskima/api` package is specifically designed to complement this model system and provides:

- **Structured API Controllers**: Organize your API endpoints with controllers and actions
- **Automatic CSRF Protection**: Built-in CSRF token validation for secure API calls
- **TYPO3 Frontend Authentication**: Leverage TYPO3's native frontend user authentication system
- **Seamless Model Integration**: Works perfectly with the BaseModel validation and error handling

```bash
composer require qkskima/api
```

## Installation

```bash
composer require qkskima/model
```

## Architecture

### Core Classes

1. **BaseModel** - Abstract base class providing all core functionality
2. **BusinessRuleInterface** - Contract for business rule validators
3. **BusinessRuleResult** - Value object for validation results
4. **TYPO3ModelTrait** - Trait to add TYPO3 specific functionality

## Usage

### Defining a Model

```php
use QkSkima\Model\BaseModel;
use Symfony\Component\Validator\Constraints as Assert;

class Order extends BaseModel
{
    protected const TABLE = 'tx_sitepackage_orders';
    
    #[Assert\NotBlank]
    public ?string $orderNumber = null;
    
    #[Assert\Email]
    public ?string $customerEmail = null;
    
    #[Assert\Valid]
    public array $orderItems = [];
    
    protected function getRelations(): array
    {
        return [
            'orderItems' => [
                'class' => OrderItem::class,
                'type' => 'many'
            ]
        ];
    }
    
    protected function getBusinessRules(): array
    {
        return [
            new OrderDateBusinessRule(),
            new OrderTotalBusinessRule(),
        ];
    }
}
```

### Creating Records

```php
// Method 1: Create with validation and save
$order = Order::create($formData);

// Method 2: Create, validate, and save separately
$order = Order::fromArray($formData);
if ($order->validate()) {
    $order->save();
}
```

### Updating Records

```php
$order = Order::find(123);
$order->update([
    'status' => 'completed',
    'customerName' => 'Updated Name'
]);
```

### Deleting Records

```php
$order = Order::find(123);
$order->destroy();
```

### Handling Validation Errors

```php
$order = Order::create($formData);

if ($order->hasErrors()) {
    $errors = $order->getErrors();
    
    // Structure:
    // [
    //     'customerEmail' => ['Invalid email address'],
    //     'orderItems.0.startDate' => ['Start date must be in the future'],
    //     'totalAmount' => ['Total amount does not match']
    // ]
}
```

## Nested Relations

The system automatically handles nested model hydration and validation:

```php
$formData = [
    'orderNumber' => 'ORD-001',
    'customerEmail' => 'test@example.com',
    'orderItems' => [
        [
            'productName' => 'Widget A',
            'quantity' => 2,
            'unitPrice' => 50.00
        ],
        [
            'productName' => 'Widget B',
            'quantity' => 1,
            'unitPrice' => 30.00
        ]
    ]
];

$order = Order::fromArray($formData);
// orderItems are automatically converted to OrderItem instances
// and validated recursively
```

This makes it easy to process the creation of nested relations through a form.

## Business Rules

Business rules provide domain-specific validation logic that runs after syntactic validation:

```php
class OrderDateBusinessRule implements BusinessRuleInterface
{
    public function getName(): string
    {
        return 'order_date_validation';
    }
    
    public function validate(BaseModel $model): BusinessRuleResult
    {
        $result = BusinessRuleResult::success();
        
        if (!$model instanceof Order) {
            return $result;
        }
        
        $orderDateTime = new \DateTime($model->orderDate);
        $now = new \DateTime();
        
        if ($orderDateTime > $now) {
            $result->addViolation(
                'orderDate',
                'Order date cannot be in the future'
            );
        }
        
        return $result;
    }
}
```

Business rules can:
- Access database via TYPO3 QueryBuilder
- Call external services
- Perform complex calculations
- Add violations to specific fields (including nested fields)

### Adding Business Rule Violations to Nested Fields

```php
public function validate(BaseModel $model): BusinessRuleResult
{
    $result = BusinessRuleResult::success();
    
    // Validate nested order items
    foreach ($model->orderItems as $index => $item) {
        if ($item->startDate < new \DateTime()) {
            $result->addViolation(
                "orderItems.{$index}.startDate",
                'Start date must be in the future'
            );
        }
    }
    
    return $result;
}
```

## Validation Flow

1. **Syntactic Validation** - Symfony constraints on properties
2. **Nested Validation** - Recursive validation of related models
3. **Business Rule Validation** - Custom domain logic (only if syntactic validation passes)

## Database Integration

The system uses TYPO3's QueryBuilder for all database operations:

- `save()` - Inserts new records or updates existing ones
- `destroy()` - Deletes records and cascades to relations
- Automatic timestamp handling (`crdate`, `tstamp`)
- Support for custom queries via `getQueryBuilder()`

### Custom Finder Methods

```php
public static function find(int $uid): ?self
{
    $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
        ->getQueryBuilderForTable(self::TABLE);
    
    $row = $queryBuilder
        ->select('*')
        ->from(self::TABLE)
        ->where(
            $queryBuilder->expr()->eq('uid', $uid)
        )
        ->executeQuery()
        ->fetchAssociative();
    
    return $row ? self::fromArray($row) : null;
}
```

## Error Structure

Errors are returned as a flat array with dot-notation for nested fields:

```php
[
    'customerEmail' => ['Invalid email address'],
    'totalAmount' => ['Must be positive', 'Does not match total'],
    'orderItems.0.quantity' => ['Must be positive'],
    'orderItems.1.startDate' => ['Start date must be in the future']
]
```

This structure makes it easy to:
- Display errors in forms
- Map errors to specific input fields
- Handle nested validation feedback

## Best Practices

1. **Always validate before save**: The `create()` method does this automatically
2. **Use business rules for domain logic**: Keep Symfony constraints for syntax/format
3. **Define relations explicitly**: Use `getRelations()` for nested models
4. **Handle errors gracefully**: Check `hasErrors()` after operations
5. **Override saveRelations/destroyRelations**: For custom cascade behavior

### Custom Business Rules with Database Access

```php
class UniqueOrderNumberRule implements BusinessRuleInterface
{
    public function validate(BaseModel $model): BusinessRuleResult
    {
        $result = BusinessRuleResult::success();
        
        if (!$model instanceof Order) {
            return $result;
        }
        
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_sitepackage_orders');
        
        $count = $queryBuilder
            ->count('uid')
            ->from('tx_sitepackage_orders')
            ->where(
                $queryBuilder->expr()->eq(
                    'order_number',
                    $queryBuilder->createNamedParameter($model->orderNumber)
                ),
                $queryBuilder->expr()->neq(
                    'uid',
                    $queryBuilder->createNamedParameter($model->uid ?? 0)
                )
            )
            ->executeQuery()
            ->fetchOne();
        
        if ($count > 0) {
            $result->addViolation(
                'orderNumber',
                'This order number already exists'
            );
        }
        
        return $result;
    }
}
```
