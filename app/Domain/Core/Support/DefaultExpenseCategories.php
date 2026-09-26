<?php

namespace App\Domain\Core\Support;

/**
 * Starter expense categories given to every new tenant (and backfilled to
 * existing ones) so the "Add expense" form is usable on day one. The tenant
 * can rename, deactivate or add to them under Expenses > Categories.
 */
class DefaultExpenseCategories
{
    public const NAMES = [
        'Rent',
        'Electricity & Water',
        'Salaries & Wages',
        'Products & Supplies',
        'Marketing & Advertising',
        'Repairs & Maintenance',
        'Internet & Phone',
        'Miscellaneous',
    ];
}
