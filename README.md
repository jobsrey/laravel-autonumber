# Laravel AutoNumber

A Laravel package for automatically generating unique sequential numbers for your Eloquent models.

## Requirements

- PHP 8.2 or higher
- Laravel 10.0, 11.0, 12.0, or 13.0

## Installation

Install the package via Composer:

```bash
composer require jobsrey/laravel-autonumber
```

### Service Provider Registration

The package uses Laravel's auto-discovery feature, so the Service Provider will be automatically registered in Laravel 5.5 and above.

For older Laravel versions or if you need manual registration, add the service provider to `config/app.php`:

```php
'providers' => [
    // Other Service Providers...
    Jobsrey\AutoNumber\AutoNumberServiceProvider::class,
],
```

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --provider="Jobsrey\AutoNumber\AutoNumberServiceProvider" --tag="config"
```

This will create a `config/autonumber.php` file with the following options:

```php
return [
    /*
     * Autonumber format
     * '?' will be replaced with the increment number.
     */
    'format' => '?',

    /*
     * The number of digits in the autonumber
     */
    'length' => 4,

    /*
     * Whether to update the autonumber value when a model is being updated.
     * Defaults to false, which means autonumber are not updated.
     */
    'onUpdate' => false,
];
```

## Migration

Run the migration to create the `auto_numbers` table:

```bash
php artisan vendor:publish --provider="Jobsrey\AutoNumber\AutoNumberServiceProvider" --tag="migrations"
php artisan migrate
```

## Usage

### Basic Usage

Add the `AutoNumberTrait` to your model and implement the `getAutoNumberOptions()` method:

```php
use Illuminate\Database\Eloquent\Model;
use Jobsrey\AutoNumber\AutoNumberTrait;

class Invoice extends Model
{
    use AutoNumberTrait;

    protected $fillable = ['number', 'customer_id', 'amount'];

    public function getAutoNumberOptions(): array
    {
        return [
            'number' => [
                'format' => 'INV-?',  // Format: INV-0001, INV-0002, etc.
                'length' => 4,         // 4 digits with zero padding
            ],
        ];
    }
}
```

Now when you create a new Invoice, the number will be automatically generated:

```php
$invoice = Invoice::create([
    'customer_id' => 1,
    'amount' => 100.00,
]);

// The number will be automatically set to: INV-0001
echo $invoice->number; // INV-0001
```

### Multiple AutoNumber Fields

You can define multiple autonumber fields for a single model:

```php
class Order extends Model
{
    use AutoNumberTrait;

    public function getAutoNumberOptions(): array
    {
        return [
            'order_number' => [
                'format' => 'ORD-?',
                'length' => 6,
            ],
            'reference' => [
                'format' => 'REF-?',
                'length' => 8,
            ],
        ];
    }
}
```

### Custom Format with Callable

You can use a callable for dynamic format generation:

```php
class Ticket extends Model
{
    use AutoNumberTrait;

    public function getAutoNumberOptions(): array
    {
        return [
            'ticket_number' => [
                'format' => function () {
                    $prefix = 'TICKET-' . date('Y');
                    return $prefix . '-?';
                },
                'length' => 5,
            ],
        ];
    }
}
```

### Per-Model Configuration Override

You can override the global configuration for specific models:

```php
class Product extends Model
{
    use AutoNumberTrait;

    public function getAutoNumberOptions(): array
    {
        return [
            'sku' => [
                'format' => 'SKU-?',
                'length' => 8,  // Override global length
            ],
        ];
    }
}
```

### Update on Model Update

By default, autonumbers are only generated when creating new models. To enable autonumber generation on updates:

1. Set `onUpdate => true` in the global config (`config/autonumber.php`)
2. Or set it per-model:

```php
class Document extends Model
{
    use AutoNumberTrait;

    public function getAutoNumberOptions(): array
    {
        return [
            'doc_number' => [
                'format' => 'DOC-?',
                'length' => 4,
                'onUpdate' => true,  // Enable update
            ],
        ];
    }
}
```

### Group Support

You can separate autonumber counters by group. This is useful when you need different sequences for different categories, branches, companies, or any other grouping criteria.

#### Static Group

Use a static string to create a fixed group:

```php
class Invoice extends Model
{
    use AutoNumberTrait;

    public function getAutoNumberOptions(): array
    {
        return [
            'number' => [
                'format' => 'INV-?',
                'length' => 4,
                'group' => 'BRANCH-001',  // Static group
            ],
        ];
    }
}
```

This will generate: `INV-0001`, `INV-0002`, etc. for BRANCH-001, and separate sequences for other branches.

#### Group from Model Field

Use a model field to create dynamic groups:

```php
class Invoice extends Model
{
    use AutoNumberTrait;

    protected $fillable = ['number', 'company_id', 'amount'];

    public function getAutoNumberOptions(): array
    {
        return [
            'number' => [
                'format' => 'INV-?',
                'length' => 4,
                'group' => $this->company_id,  // Group by company
            ],
        ];
    }
}
```

Each company will have its own invoice sequence.

#### Dynamic Group with Callable

Use a callable for complex group logic:

```php
class Invoice extends Model
{
    use AutoNumberTrait;

    protected $fillable = ['number', 'company_id', 'branch_id', 'amount'];

    public function getAutoNumberOptions(): array
    {
        return [
            'number' => [
                'format' => 'INV-?',
                'length' => 4,
                'group' => fn() => $this->company_id . '-' . $this->branch_id,
            ],
        ];
    }
}
```

This creates groups like `COMPANY-001-BRANCH-A`, `COMPANY-001-BRANCH-B`, etc.

#### String + Field Combination

Combine static text with model fields:

```php
class Invoice extends Model
{
    use AutoNumberTrait;

    protected $fillable = ['number', 'company_id', 'amount'];

    public function getAutoNumberOptions(): array
    {
        return [
            'number' => [
                'format' => 'INV-?',
                'length' => 4,
                'group' => $this->company_id . '_test',  // Company + suffix
            ],
        ];
    }
}
```

#### Backward Compatibility

Models without a `group` parameter will continue to work with a global counter:

```php
class LegacyInvoice extends Model
{
    use AutoNumberTrait;

    public function getAutoNumberOptions(): array
    {
        return [
            'number' => [
                'format' => 'INV-?',
                'length' => 4,
                // No group parameter - uses global counter
            ],
        ];
    }
}
```

#### Multiple Groups with Multiple Fields

You can use different groups for different autonumber fields:

```php
class Order extends Model
{
    use AutoNumberTrait;

    public function getAutoNumberOptions(): array
    {
        return [
            'order_number' => [
                'format' => 'ORD-?',
                'length' => 6,
                'group' => $this->company_id,
            ],
            'reference' => [
                'format' => 'REF-?',
                'length' => 8,
                'group' => fn() => $this->company_id . '-' . $this->region_id,
            ],
        ];
    }
}
```

## Configuration Options

### format

The pattern for the autonumber. The `?` placeholder will be replaced with the increment number.

- Type: `string` or `callable`
- Default: `'?'`
- Example: `'INV-?'` will generate `INV-0001`, `INV-0002`, etc.

### length

The number of digits for zero-padding the increment number.

- Type: `int`
- Default: `4`
- Example: `4` will generate `0001`, `0002`, etc.

### onUpdate

Whether to regenerate the autonumber when the model is updated.

- Type: `bool`
- Default: `false`
- Note: When `false`, autonumbers are only generated on model creation

### group

The group identifier for separating counters per group.

- Type: `string` or `callable`
- Default: `null`
- Example: `'group' => $this->company_id` or `'group' => fn() => $this->company_id . '-' . $this->branch_id`
- Note: If `null`, the counter is global per model. Each group maintains its own sequence.

## License

This package is open-sourced software licensed under the [MIT license](LICENSE).

## Support

For issues, questions, or contributions, please visit the [GitHub repository](https://github.com/jobsrey/laravel-autonumber).
