<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Inventory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    /**
     * Get all products
     */
// public function index(Request $request)
// {
//     try {
//         $query = Product::with([
//             'category',
//             'primaryImage',
//             'images',
//             'variants.inventory.branch',
//             'creator',
//             'inventory.branch', // 👈 IMPORTANT
//             'activeDiscounts'
//         ]);

//         // Filters
//         if ($request->has('category_id')) {
//             $query->where('category_id', $request->category_id);
//         }

//         if ($request->has('is_active')) {
//             $query->where('is_active', $request->is_active);
//         }

//         if ($request->has('has_variants')) {
//             $query->where('has_variants', $request->has_variants);
//         }

//         if ($request->has('search')) {
//             $search = $request->search;
//             $query->where(function ($q) use ($search) {
//                 $q->where('product_name', 'like', "%{$search}%")
//                   ->orWhere('sku', 'like', "%{$search}%")
//                   ->orWhere('barcode', 'like', "%{$search}%");
//             });
//         }

//         $perPage = $request->get('per_page', 15);
//         $products = $query->latest()->paginate($perPage);

//         // 🔥 SAME LOGIC AS SHOW()
//         // $products->getCollection()->transform(function ($product) {
//         //     $product->total_stock = $product->inventory->sum('quantity');
//         //     $product->available_stock = $product->inventory->sum('available_quantity');
//         //     return $product;
//         // });
//         $products->getCollection()->transform(function ($product) {
//             // Add computed fields as attributes
//             $product->total_stock = $product->inventory->sum('quantity');
//             $product->available_stock = $product->inventory->sum('available_quantity');
            
//             // Return the original product with new attributes attached
//             return $product;
//         });

//         return response()->json([
//             'success' => true,
//             'data' => $products
//         ], 200);

//     } catch (\Exception $e) {
//         return response()->json([
//             'success' => false,
//             'message' => 'Failed to fetch products',
//             'error' => $e->getMessage()
//         ], 500);
//     }
// }


public function index(Request $request)
{
    try {
        // Build the query
        $query = Product::query();

        // Apply filters
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        if ($request->has('has_variants')) {
            $query->where('has_variants', $request->has_variants);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('product_name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        $perPage = $request->get('per_page', 15);
        $products = $query->latest()->paginate($perPage);

        // Load relationships AFTER pagination
        $products->load([
            'category',
            'primaryImage',
            'images',
            'variants.inventory.branch',
            'inventory.branch',
            'creator',
            'activeDiscounts'
        ]);

        // Calculate stock
        $products->getCollection()->transform(function ($product) {
            $productStock = $product->inventory->sum('quantity');
            $productAvailable = $product->inventory->sum('available_quantity');
            
            $variantStock = 0;
            $variantAvailable = 0;
            foreach ($product->variants as $variant) {
                $variantStock += $variant->inventory->sum('quantity');
                $variantAvailable += $variant->inventory->sum('available_quantity');
            }
            
            $product->total_stock = $productStock + $variantStock;
            $product->available_stock = $productAvailable + $variantAvailable;
            
            return $product;
        });

        return response()->json([
            'success' => true,
            'data' => $products
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to fetch products',
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString() // Add this for debugging
        ], 500);
    }
}

    /**
     * Get single product
     */
    // public function show($id)
    // {
    //     try {
    //         $product = Product::with([
    //             'category',
    //             'images',
    //             'variants.inventory.branch',
    //             'inventory.branch',
    //             'activeDiscounts',
    //             'creator'
    //         ])->findOrFail($id);

    //         // Calculate total stock
    //         $product->total_stock = $product->inventory->sum('quantity');
    //         $product->available_stock = $product->inventory->sum('available_quantity');

    //         return response()->json([
    //             'success' => true,
    //             'data' => $product
    //         ], 200);

    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Product not found',
    //             'error' => $e->getMessage()
    //         ], 404);
    //     }
    // }

    public function show($id)
{
    try {
        $product = Product::with([
            'category',
            'images',
            'variants.inventory.branch',
            'inventory.branch',
            'activeDiscounts',
            'creator'
        ])->findOrFail($id);

        // Calculate product-level inventory
        $productStock = $product->inventory->sum('quantity');
        $productAvailable = $product->inventory->sum('available_quantity');
        
        // Calculate variant-level inventory
        $variantStock = 0;
        $variantAvailable = 0;
        
        if ($product->has_variants && $product->variants->isNotEmpty()) {
            foreach ($product->variants as $variant) {
                $variantStock += $variant->inventory->sum('quantity');
                $variantAvailable += $variant->inventory->sum('available_quantity');
            }
        }
        
        // Set totals
        $product->total_stock = $productStock + $variantStock;
        $product->available_stock = $productAvailable + $variantAvailable;

        return response()->json([
            'success' => true,
            'data' => $product
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Product not found',
            'error' => $e->getMessage()
        ], 404);
    }
}


    /**
     * Create product
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_name' => 'required|string|max:255',
            'sku' => 'nullable|string|unique:products,sku',
            'barcode' => 'nullable|string|unique:products,barcode',
            'category_id' => 'nullable|exists:categories,id',
            'description' => 'nullable|string',
            'unit' => 'required|string',
            'weight' => 'nullable|numeric',
            'dimensions' => 'nullable|string',
            'color' => 'nullable|string',
            'cost_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'has_variants' => 'boolean',
            'low_stock_alert' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
            'variants' => 'nullable|array',
            'variants.*.variant_name' => 'required_with:variants|string',
            'variants.*.variant_value' => 'required_with:variants|string',
            'variants.*.cost_price' => 'nullable|numeric',
            'variants.*.selling_price' => 'nullable|numeric',
            'variants.*.additional_price' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Generate SKU if not provided
            if (!$request->sku) {
                $request->merge(['sku' => $this->generateSKU()]);
            }

            // Generate barcode if not provided
            if (!$request->barcode) {
                $request->merge(['barcode' => $this->generateBarcode()]);
            }

            // Create product
            $product = Product::create([
                'product_name' => $request->product_name,
                'sku' => $request->sku,
                'barcode' => $request->barcode,
                'category_id' => $request->category_id,
                'description' => $request->description,
                'unit' => $request->unit,
                'weight' => $request->weight,
                'dimensions' => $request->dimensions,
                'color' => $request->color,
                'cost_price' => $request->cost_price,
                'selling_price' => $request->selling_price,
                'has_variants' => $request->has_variants ?? false,
                'low_stock_alert' => $request->low_stock_alert ?? 10,
                'is_active' => $request->is_active ?? true,
                'created_by' => auth()->id(),
            ]);

            // Handle image uploads
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $index => $image) {
                    $imageName = time() . '_' . $index . '_' . $image->getClientOriginalName();
                    $imagePath = $image->storeAs('products', $imageName, 'public');
                    
                    ProductImage::create([
                        'product_id' => $product->id,
                        'image_path' => $imagePath,
                        'is_primary' => $index === 0, // First image is primary
                        'sort_order' => $index,
                    ]);
                }
            }

            // Create variants if provided
            if ($request->has('variants') && $request->has_variants) {
                foreach ($request->variants as $variantData) {
                    ProductVariant::create([
                        'product_id' => $product->id,
                        'variant_name' => $variantData['variant_name'],
                        'variant_value' => $variantData['variant_value'],
                        'sku' => $this->generateSKU(),
                        'barcode' => $this->generateBarcode(),
                        'cost_price' => $variantData['cost_price'] ?? $product->cost_price,
                        'selling_price' => $variantData['selling_price'] ?? $product->selling_price,
                        'additional_price' => $variantData['additional_price'] ?? 0,
                        'is_active' => true,
                    ]);
                }
            }

            DB::commit();

            $product->load(['category', 'images', 'variants']);

            return response()->json([
                'success' => true,
                'message' => 'Product created successfully',
                'data' => $product
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create product',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update product
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'product_name' => 'required|string|max:255',
            'sku' => 'nullable|string|unique:products,sku,' . $id,
            'barcode' => 'nullable|string|unique:products,barcode,' . $id,
            'category_id' => 'nullable|exists:categories,id',
            'description' => 'nullable|string',
            'unit' => 'required|string',
            'weight' => 'nullable|numeric',
            'dimensions' => 'nullable|string',
            'color' => 'nullable|string',
            'cost_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'has_variants' => 'boolean',
            'low_stock_alert' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $product = Product::findOrFail($id);

            $product->update($request->except('images'));

            // Handle new image uploads
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $index => $image) {
                    $imageName = time() . '_' . $index . '_' . $image->getClientOriginalName();
                    $imagePath = $image->storeAs('products', $imageName, 'public');
                    
                    $existingImagesCount = $product->images()->count();
                    
                    ProductImage::create([
                        'product_id' => $product->id,
                        'image_path' => $imagePath,
                        'is_primary' => $existingImagesCount === 0 && $index === 0,
                        'sort_order' => $existingImagesCount + $index,
                    ]);
                }
            }

            DB::commit();

            $product->load(['category', 'images', 'variants']);

            return response()->json([
                'success' => true,
                'message' => 'Product updated successfully',
                'data' => $product
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update product',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete product
     */
    public function destroy($id)
    {
        try {
            $product = Product::findOrFail($id);

            // Check if product has inventory
            if ($product->inventory()->sum('quantity') > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete product. It has inventory in stock.'
                ], 400);
            }

            // Delete images
            foreach ($product->images as $image) {
                Storage::disk('public')->delete($image->image_path);
            }

            $product->delete();

            return response()->json([
                'success' => true,
                'message' => 'Product deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete product',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate random SKU
     */
    private function generateSKU()
    {
        do {
            $sku = 'SKU' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
        } while (Product::where('sku', $sku)->exists() || ProductVariant::where('sku', $sku)->exists());

        return $sku;
    }

    /**
     * Generate EAN-13 barcode
     */
    private function generateBarcode()
    {
        do {
            $barcode = '20' . str_pad(rand(0, 99999999999), 11, '0', STR_PAD_LEFT);
        } while (Product::where('barcode', $barcode)->exists() || ProductVariant::where('barcode', $barcode)->exists());

        return $barcode;
    }

    /**
     * Get product stock across all branches
     */
    public function getStock($id)
    {
        try {
            $product = Product::with(['inventory.branch', 'variants.inventory.branch'])
                ->findOrFail($id);

            $stockData = [
                'product' => [
                    'id' => $product->id,
                    'name' => $product->product_name,
                    'sku' => $product->sku,
                    'barcode' => $product->barcode,
                ],
                'total_quantity' => 0,
                'total_available' => 0,
                'stock_by_branch' => []
            ];

            // If product has no variants
            if (!$product->has_variants) {
                foreach ($product->inventory as $inv) {
                    $stockData['total_quantity'] += $inv->quantity;
                    $stockData['total_available'] += $inv->available_quantity;
                    
                    $stockData['stock_by_branch'][] = [
                        'branch_id' => $inv->branch->id,
                        'branch_name' => $inv->branch->branch_name,
                        'branch_type' => $inv->branch->branch_type,
                        'quantity' => $inv->quantity,
                        'reserved' => $inv->reserved_quantity,
                        'available' => $inv->available_quantity,
                        'is_low_stock' => $inv->isLowStock(),
                    ];
                }
            } else {
                // If product has variants
                $stockData['variants'] = [];
                
                foreach ($product->variants as $variant) {
                    $variantStock = [
                        'variant_id' => $variant->id,
                        'variant_name' => $variant->variant_name,
                        'variant_value' => $variant->variant_value,
                        'sku' => $variant->sku,
                        'total_quantity' => 0,
                        'total_available' => 0,
                        'stock_by_branch' => []
                    ];

                    foreach ($variant->inventory as $inv) {
                        $variantStock['total_quantity'] += $inv->quantity;
                        $variantStock['total_available'] += $inv->available_quantity;
                        
                        $variantStock['stock_by_branch'][] = [
                            'branch_id' => $inv->branch->id,
                            'branch_name' => $inv->branch->branch_name,
                            'branch_type' => $inv->branch->branch_type,
                            'quantity' => $inv->quantity,
                            'reserved' => $inv->reserved_quantity,
                            'available' => $inv->available_quantity,
                            'is_low_stock' => $inv->isLowStock(),
                        ];
                    }

                    $stockData['total_quantity'] += $variantStock['total_quantity'];
                    $stockData['total_available'] += $variantStock['total_available'];
                    $stockData['variants'][] = $variantStock;
                }
            }

            return response()->json([
                'success' => true,
                'data' => $stockData
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch product stock',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search product by barcode (for POS/PDA)
     */
    public function searchByBarcode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'barcode' => 'required|string',
            'branch_id' => 'nullable|exists:branches,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Search in products
            $product = Product::where('barcode', $request->barcode)
                ->with(['category', 'primaryImage', 'activeDiscounts'])
                ->first();

            if ($product) {
                $data = [
                    'type' => 'product',
                    'product' => $product,
                    'variant' => null,
                ];

                // Get stock info if branch_id provided
                if ($request->branch_id) {
                    $inventory = Inventory::where('product_id', $product->id)
                        ->where('branch_id', $request->branch_id)
                        ->whereNull('variant_id')
                        ->first();

                    $data['stock'] = $inventory ? [
                        'quantity' => $inventory->quantity,
                        'available' => $inventory->available_quantity,
                        'reserved' => $inventory->reserved_quantity,
                    ] : null;
                }

                return response()->json([
                    'success' => true,
                    'data' => $data
                ], 200);
            }

            // Search in product variants
            $variant = ProductVariant::where('barcode', $request->barcode)
                ->with(['product.category', 'product.primaryImage', 'product.activeDiscounts'])
                ->first();

            if ($variant) {
                $data = [
                    'type' => 'variant',
                    'product' => $variant->product,
                    'variant' => $variant,
                ];

                // Get stock info if branch_id provided
                if ($request->branch_id) {
                    $inventory = Inventory::where('product_id', $variant->product_id)
                        ->where('variant_id', $variant->id)
                        ->where('branch_id', $request->branch_id)
                        ->first();

                    $data['stock'] = $inventory ? [
                        'quantity' => $inventory->quantity,
                        'available' => $inventory->available_quantity,
                        'reserved' => $inventory->reserved_quantity,
                    ] : null;
                }

                return response()->json([
                    'success' => true,
                    'data' => $data
                ], 200);
            }

            return response()->json([
                'success' => false,
                'message' => 'Product not found with this barcode'
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Search failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get low stock products
     */
    public function getLowStockProducts(Request $request)
    {
        try {
            $branchId = $request->get('branch_id');

            $query = Inventory::with(['product.category', 'product.primaryImage', 'variant', 'branch'])
                ->whereRaw('available_quantity <= reorder_point');

            if ($branchId) {
                $query->where('branch_id', $branchId);
            }

            $lowStockItems = $query->get()->map(function($inventory) {
                return [
                    'product_id' => $inventory->product->id,
                    'product_name' => $inventory->product->product_name,
                    'sku' => $inventory->variant ? $inventory->variant->sku : $inventory->product->sku,
                    'variant' => $inventory->variant ? $inventory->variant->variant_name . ': ' . $inventory->variant->variant_value : null,
                    'branch_id' => $inventory->branch->id,
                    'branch_name' => $inventory->branch->branch_name,
                    'current_stock' => $inventory->available_quantity,
                    'reorder_point' => $inventory->reorder_point,
                    'quantity_needed' => $inventory->reorder_point - $inventory->available_quantity,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $lowStockItems,
                'count' => $lowStockItems->count()
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch low stock products',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get product profitability report
     */
    public function profitabilityReport(Request $request)
    {
        try {
            $query = Product::with(['category', 'primaryImage'])
                ->where('is_active', true);

            // Filter by category
            if ($request->has('category_id')) {
                $query->where('category_id', $request->category_id);
            }

            // Sort by profit margin
            $sortBy = $request->get('sort_by', 'profit_margin');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            $products = $query->get()->map(function($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->product_name,
                    'sku' => $product->sku,
                    'category' => $product->category ? $product->category->category_name : null,
                    'cost_price' => $product->cost_price,
                    'selling_price' => $product->selling_price,
                    'profit_per_unit' => $product->selling_price - $product->cost_price,
                    'profit_margin' => round($product->profit_margin, 2) . '%',
                    'total_stock' => $product->inventory->sum('quantity'),
                    'potential_profit' => ($product->selling_price - $product->cost_price) * $product->inventory->sum('quantity'),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $products
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate profitability report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete product image
     */
    public function deleteImage($imageId)
    {
        try {
            $image = ProductImage::findOrFail($imageId);
            
            // Delete from storage
            Storage::disk('public')->delete($image->image_path);
            
            $image->delete();

            return response()->json([
                'success' => true,
                'message' => 'Image deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete image',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Set primary image
     */
    public function setPrimaryImage($productId, $imageId)
    {
        try {
            $product = Product::findOrFail($productId);
            
            // Remove primary status from all images
            ProductImage::where('product_id', $productId)
                ->update(['is_primary' => false]);
            
            // Set new primary image
            $image = ProductImage::where('id', $imageId)
                ->where('product_id', $productId)
                ->firstOrFail();
            
            $image->update(['is_primary' => true]);

            return response()->json([
                'success' => true,
                'message' => 'Primary image updated successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to set primary image',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get product statistics
     */
    public function statistics()
    {
        try {
            $stats = [
                'total_products' => Product::count(),
                'active_products' => Product::where('is_active', true)->count(),
                'inactive_products' => Product::where('is_active', false)->count(),
                'products_with_variants' => Product::where('has_variants', true)->count(),
                'low_stock_items' => Inventory::whereRaw('available_quantity <= reorder_point')->count(),
                'out_of_stock_items' => Inventory::where('quantity', 0)->count(),
                'total_stock_value' => DB::table('inventory')
                    ->join('products', 'inventory.product_id', '=', 'products.id')
                    ->selectRaw('SUM(inventory.quantity * products.cost_price) as total')
                    ->value('total'),
                'products_by_category' => DB::table('products')
                    ->join('categories', 'products.category_id', '=', 'categories.id')
                    ->select('categories.category_name', DB::raw('COUNT(*) as count'))
                    ->groupBy('categories.category_name')
                    ->get(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}