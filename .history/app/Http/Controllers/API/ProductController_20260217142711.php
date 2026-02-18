<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductDiscount;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Inventory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\IOFactory;


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
    // Fix for PUT/PATCH with form-data
    if ($request->isMethod('PUT') || $request->isMethod('PATCH')) {
        $request->merge($request->all());
    }

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

        $product->update([
            'product_name' => $request->product_name,
            'sku' => $request->sku ?? $product->sku,
            'barcode' => $request->barcode ?? $product->barcode,
            'category_id' => $request->category_id ?? $product->category_id,
            'description' => $request->description,
            'unit' => $request->unit,
            'weight' => $request->weight,
            'dimensions' => $request->dimensions,
            'color' => $request->color,
            'cost_price' => $request->cost_price,
            'selling_price' => $request->selling_price,
            'has_variants' => $request->has_variants ?? $product->has_variants,
            'low_stock_alert' => $request->low_stock_alert ?? $product->low_stock_alert,
            'is_active' => $request->is_active ?? $product->is_active,
        ]);

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




public function generateDiscountTemplate(Request $request)
{
    $validator = Validator::make($request->all(), [
        'category_id' => 'nullable|exists:categories,id',
        'start_date' => 'required|date',
        'end_date' => 'required|date|after:start_date',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'message' => 'Validation error',
            'errors' => $validator->errors()
        ], 422);
    }

    try {
        // Get products
        $query = Product::with(['category', 'variants'])
            ->where('is_active', true);

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        $products = $query->get();

        // Create spreadsheet
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Set headers
        $sheet->setCellValue('A1', 'Product ID');
        $sheet->setCellValue('B1', 'Product Name');
        $sheet->setCellValue('C1', 'SKU');
        $sheet->setCellValue('D1', 'Variant ID');
        $sheet->setCellValue('E1', 'Variant Name');
        $sheet->setCellValue('F1', 'Current Price (KWD)');
        $sheet->setCellValue('G1', 'Discount Type');
        $sheet->setCellValue('H1', 'Discount Value');
        $sheet->setCellValue('I1', 'Start Date');
        $sheet->setCellValue('J1', 'End Date');

        // Style header row
        $sheet->getStyle('A1:J1')->getFont()->setBold(true);
        $sheet->getStyle('A1:J1')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFD3D3D3');

        // Add data
        $row = 2;
        foreach ($products as $product) {
            if (!$product->has_variants) {
                // Product without variants
                $sheet->setCellValue('A' . $row, $product->id);
                $sheet->setCellValue('B' . $row, $product->product_name);
                $sheet->setCellValue('C' . $row, $product->sku);
                $sheet->setCellValue('D' . $row, '');
                $sheet->setCellValue('E' . $row, '');
                $sheet->setCellValue('F' . $row, $product->selling_price);
                $sheet->setCellValue('G' . $row, 'Percentage'); // Default
                $sheet->setCellValue('H' . $row, ''); // User fills this
                $sheet->setCellValue('I' . $row, $request->start_date);
                $sheet->setCellValue('J' . $row, $request->end_date);
                $row++;
            } else {
                // Product with variants
                foreach ($product->variants as $variant) {
                    $sheet->setCellValue('A' . $row, $product->id);
                    $sheet->setCellValue('B' . $row, $product->product_name);
                    $sheet->setCellValue('C' . $row, $product->sku);
                    $sheet->setCellValue('D' . $row, $variant->id);
                    $sheet->setCellValue('E' . $row, $variant->variant_name . ': ' . $variant->variant_value);
                    $sheet->setCellValue('F' . $row, $variant->selling_price);
                    $sheet->setCellValue('G' . $row, 'Percentage');
                    $sheet->setCellValue('H' . $row, '');
                    $sheet->setCellValue('I' . $row, $request->start_date);
                    $sheet->setCellValue('J' . $row, $request->end_date);
                    $row++;
                }
            }
        }

        // Auto-size columns
        foreach (range('A', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Add data validation for Discount Type
        $validation = $sheet->getCell('G2')->getDataValidation();
        $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
        $validation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_INFORMATION);
        $validation->setAllowBlank(false);
        $validation->setShowInputMessage(true);
        $validation->setShowErrorMessage(true);
        $validation->setShowDropDown(true);
        $validation->setErrorTitle('Input error');
        $validation->setError('Value must be Percentage or Fixed Amount');
        $validation->setPromptTitle('Select type');
        $validation->setPrompt('Please select discount type');
        $validation->setFormula1('"Percentage,Fixed Amount"');

        // Apply validation to all rows
        for ($i = 2; $i < $row; $i++) {
            $sheet->getCell('G' . $i)->setDataValidation(clone $validation);
        }

        // Save file
        $fileName = 'bulk_discount_template_' . date('Ymd_His') . '.xlsx';
        
        // Create exports directory if not exists
        Storage::disk('public')->makeDirectory('exports');
        
        $filePath = 'exports/' . $fileName;
        $fullPath = storage_path('app/public/' . $filePath);
        
        $writer = new Xlsx($spreadsheet);
        $writer->save($fullPath);

        return response()->json([
            'success' => true,
            'message' => 'Excel template generated successfully',
            'data' => [
                'file_name' => $fileName,
                'file_path' => $filePath,
                'download_url' => url('storage/' . $filePath),
                'total_items' => $row - 2,
            ]
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to generate template',
            'error' => $e->getMessage()
        ], 500);
    }
}

    /**
     * Import bulk discount from Excel
     */
    public function importBulkDiscount(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:xlsx,xls|max:10240',
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
            $file = $request->file('file');
            $spreadsheet = IOFactory::load($file->getPathname());
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray();

            // Skip header row
            array_shift($rows);

            $created = 0;
            $updated = 0;
            $errors = [];

            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2; // +2 because we skipped header and arrays are 0-indexed

                // Skip empty rows
                if (empty($row[0]) || empty($row[7])) {
                    continue;
                }

                try {
                    $productId = $row[0];
                    $variantId = !empty($row[3]) ? $row[3] : null;

                    $discountType = $row[6];
                    $discountValue = $row[7];
                    $startDate = $row[8];
                    $endDate = $row[9];

                    // Validate discount type
                    if (!in_array($discountType, ['Percentage', 'Fixed Amount'])) {
                        $errors[] = "Row {$rowNumber}: Invalid discount type '{$discountType}'";
                        continue;
                    }

                    // Validate discount value
                    if (!is_numeric($discountValue) || $discountValue < 0) {
                        $errors[] = "Row {$rowNumber}: Invalid discount value";
                        continue;
                    }

                    // Check if discount already exists
                    $existingDiscount = ProductDiscount::where('product_id', $productId)
                        ->where('variant_id', $variantId)
                        ->where('start_date', $startDate)
                        ->where('end_date', $endDate)
                        ->first();

                    if ($existingDiscount) {
                        // Update existing
                        $existingDiscount->update([
                            'discount_type' => $discountType,
                            'discount_value' => $discountValue,
                            'is_active' => true,
                        ]);
                        $updated++;
                    } else {
                        // Create new
                        ProductDiscount::create([
                            'product_id' => $productId,
                            'variant_id' => $variantId,
                            'discount_type' => $discountType,
                            'discount_value' => $discountValue,
                            'start_date' => $startDate,
                            'end_date' => $endDate,
                            'is_active' => true,
                            'created_by' => auth()->id(),
                        ]);
                        $created++;
                    }

                } catch (\Exception $e) {
                    $errors[] = "Row {$rowNumber}: " . $e->getMessage();
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Bulk discount imported successfully',
                'data' => [
                    'created' => $created,
                    'updated' => $updated,
                    'errors' => $errors,
                    'total_processed' => $created + $updated,
                ]
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to import discounts',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get active discounts
     */
    public function getActiveDiscounts(Request $request)
    {
        try {
            $query = ProductDiscount::with(['product.category', 'product.primaryImage', 'variant', 'creator'])
                ->where('is_active', true)
                ->whereDate('start_date', '<=', now())
                ->whereDate('end_date', '>=', now());

            // Filter by product
            if ($request->has('product_id')) {
                $query->where('product_id', $request->product_id);
            }

            // Filter by date range
            if ($request->has('start_date')) {
                $query->whereDate('start_date', '>=', $request->start_date);
            }

            if ($request->has('end_date')) {
                $query->whereDate('end_date', '<=', $request->end_date);
            }

            $discounts = $query->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $discounts
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch discounts',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete discount
     */
    public function deleteDiscount($id)
    {
        try {
            $discount = ProductDiscount::findOrFail($id);
            $discount->delete();

            return response()->json([
                'success' => true,
                'message' => 'Discount deleted successfully'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete discount',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}