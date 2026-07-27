<?php

namespace App\Http\Controllers\Core;

use App\Domain\Core\Models\Product;
use App\Domain\Core\Models\ProductCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Core\StoreProductRequest;
use App\Http\Requests\Core\UpdateProductRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class ProductController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Product::class, 'product');
    }

    public function index(): View
    {
        return view('core.products.index', [
            'products' => Product::with('category')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('core.products.create', [
            'categories' => ProductCategory::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        Product::create($request->validated());

        return redirect()->route('products.index')->with('status', 'Product created.');
    }

    public function edit(Product $product): View
    {
        return view('core.products.edit', [
            'product' => $product,
            'categories' => ProductCategory::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $product->update($request->validated());

        return redirect()->route('products.index')->with('status', 'Product updated.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->update(['is_active' => false]);

        return redirect()->route('products.index')->with('status', 'Product deactivated.');
    }
}
