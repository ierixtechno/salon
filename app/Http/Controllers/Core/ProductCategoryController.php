<?php

namespace App\Http\Controllers\Core;

use App\Domain\Core\Models\ProductCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\StoreProductCategoryRequest;
use App\Http\Requests\Core\UpdateProductCategoryRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class ProductCategoryController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(ProductCategory::class, 'product_category');
    }

    public function index(): View
    {
        return view('core.product-categories.index', [
            'categories' => ProductCategory::withCount('products')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('core.product-categories.create');
    }

    public function store(StoreProductCategoryRequest $request): RedirectResponse
    {
        ProductCategory::create($request->validated());

        return redirect()->route('product-categories.index')->with('status', 'Category created.');
    }

    public function edit(ProductCategory $productCategory): View
    {
        return view('core.product-categories.edit', ['category' => $productCategory]);
    }

    public function update(UpdateProductCategoryRequest $request, ProductCategory $productCategory): RedirectResponse
    {
        $productCategory->update($request->validated());

        return redirect()->route('product-categories.index')->with('status', 'Category updated.');
    }

    public function destroy(ProductCategory $productCategory): RedirectResponse
    {
        $productCategory->update(['is_active' => false]);

        return redirect()->route('product-categories.index')->with('status', 'Category deactivated.');
    }
}
