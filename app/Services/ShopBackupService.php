<?php

namespace App\Services;

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ShopBackupService extends BackupService
{
    /**
     * @var ShopApiService
     */
    protected ShopApiService $apiService;

    /**
     * Tables to be backed up in hierarchical order (parent → child)
     */
    protected array $tableMacrosMap = [
        'products' => [
            'fields' => [
                'macro' => 'productTableFields'
            ],
        ],
        'product_images' => [
            'fields' => [
                'macro' => 'productImageTableFields'
            ],
            'fk' => [
                'macro' => 'addProductImageForeignKey',
                'keys' => [
                    [
                        'columns' => ['product_id'],
                        'references' => 'products'
                    ]
                ]
            ],
        ],
        'variants' => [
            'fields' => [
                'macro' => 'variantTableFields'
            ],
            'fk' => [
                'macro' => 'addVariantForeignKey',
                'keys' => [
                    [
                        'columns' => ['product_id'],
                        'references' => 'products'
                    ],
                ]
            ],
        ],
        'variant_images' => [
            'fields' => [
                'macro' => 'variantImageTableFields'
            ],
            'fk' => [
                'macro' => 'addVariantImageForeignKey',
                'keys' => [
                    [
                        'columns' => ['variant_id'],
                        'references' => 'variants'
                    ]
                ]
            ],
        ]
    ];

    public function __construct(ShopApiService $apiService = null)
    {
        parent::__construct();
        $this->apiService = $apiService ?? new ShopApiService();
    }

    protected function getBackupName(): string
    {
        return 'shop';
    }

    /**
     * @throws Exception
     */
    protected function executeBackup(): void
    {
        $this->backupData('products', 'getProducts', 'processProductData');
        $this->backupData('variants', 'getVariants', 'processVariantData');
        $this->backupData('variant_images', 'getVariantImages', 'processVariantImageData');
    }

    /**
     * @throws Exception
     */
    protected function backupData(string $tableName, string $apiMethod, string $processData): void
    {
        Log::info("Starting {$tableName} backup");

        $page = 1;
        $limit = config('services.shop.batch_size_limit', 100);
        $total = 0;

        do {
            try {
                $responseData = $this->apiService->$apiMethod($page, $limit);
                $data = $responseData['data'] ?? [];
                $shouldContinue = $responseData['meta']['has_more_pages'] ?? false;

                if (empty($data)) {
                    break;
                }

                $this->{$processData}($data);

                $total += count($data);
                $page++;
            } catch (Exception $e) {
                Log::error("Error backing up {$tableName}: " . $e->getMessage());
                throw $e;
            }
        } while ($shouldContinue);

        Log::info("Completed {$tableName} backup. Total {$tableName}: {$total}");
    }

    protected function processProductData(array $products): void
    {
        $productBatch = [];
        $productImagesBatch = [];

        foreach ($products as $product) {
            $productId = (string)Str::uuid7();
            $productImage = $product['image'];

            $productBatch[] = [
                'id' => $productId,
                'product_uuid' => $product['id'],
                'name' => $product['title'],
                'product_price' => $product['price'],
                'product_handle' => $product['handle'],
                'created_at' => $this->formatDateTime($product['created_at']),
                'updated_at' => $this->formatDateTime($product['updated_at']),
            ];

            if(!empty($productImage)) {
                $productImagesBatch[] = [
                    'id' => (string)Str::uuid7(),
                    'product_id' => $productId,
                    'product_uuid' => $product['id'],
                    'url' => $productImage['url'],
                    'created_at' => $this->formatDateTime($productImage['created_at']),
                    'updated_at' => $this->formatDateTime($productImage['updated_at']),
                ];
            }
        }

        $productsTable = $this->getTemporaryTableName('products');
        $productImagesTable = $this->getTemporaryTableName('product_images');

        $this->insertInChunks($productsTable, $productBatch);
        $this->insertInChunks($productImagesTable, $productImagesBatch);
    }

    /**
     * @throws Exception
     */
    protected function processVariantData(array $variants): void
    {
        $batch = [];

        $products = DB::table($this->getTemporaryTableName('products'))
            ->select(['id', 'product_uuid'])
            ->whereIn('product_uuid', collect($variants)->pluck('product_id'))
            ->get();


        foreach ($variants as $variant) {
            $product = $products->where('product_uuid', '=', $variant['product_id'])->first();

            if(empty($product)) {
                Log::error('Product missing.', [
                    'product_uuid' => $variant['product_id']
                ]);

                throw new Exception("Product with product_uuid: {$variant['product_id']} is missing.");
            }

            $batch[] = [
                'id' => (string)Str::uuid7(),
                'product_id' => $product->id,
                'variant_uuid' => $variant['id'],
                'product_uuid' => $variant['product_id'],
                'variant_price' => $variant['price'],
                'variant_handle' => $variant['handle'],
                'created_at' => $this->formatDateTime($variant['created_at']),
                'updated_at' => $this->formatDateTime($variant['updated_at']),
            ];
        }

        $variantsTable = $this->getTemporaryTableName('variants');

        $this->insertInChunks($variantsTable, $batch);
    }

    /**
     * @throws Exception
     */
    protected function processVariantImageData(array $variantImages): void
    {
        $batch = [];

        $variants = DB::table($this->getTemporaryTableName('variants'))
            ->select(['id', 'variant_uuid'])
            ->whereIn('variant_uuid', collect($variantImages)->pluck('variant_id'))
            ->get();


        foreach ($variantImages as $variantImage) {
            $variant = $variants->where('variant_uuid', '=', $variantImage['variant_id'])->first();

            if(empty($variant)) {
                Log::error('Variant missing.', [
                    'variant_uuid' => $variantImage['variant_id']
                ]);

                throw new Exception("Variant with variant_uuid: {$variantImage['variant_id']} is missing.");
            }

            $batch[] = [
                'id' => (string)Str::uuid7(),
                'variant_id' => $variant->id,
                'variant_uuid' => $variantImage['variant_id'],
                'url' => $variantImage['image']['url'],
                'created_at' => $this->formatDateTime($variantImage['created_at']),
                'updated_at' => $this->formatDateTime($variantImage['updated_at']),
            ];
        }

        $variantImagesTable = $this->getTemporaryTableName('variant_images');

        $this->insertInChunks($variantImagesTable, $batch);
    }

    protected function formatDateTime(string $dateTime): string
    {
        return Carbon::parse($dateTime)->format('Y-m-d H:i:s');
    }

    protected function insertInChunks(string $tableName, array $records, int $chunkSize = 1000): void
    {
        foreach (array_chunk($records, $chunkSize) as $chunk) {
            DB::table($tableName)->insert($chunk);
        }
    }
}
