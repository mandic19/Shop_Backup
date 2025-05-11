<?php

namespace App\Providers;

use App\Support\BlueprintMacros;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class DatabaseMacroServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Base fields for shop entities
        Blueprint::macro('shopEntityFields', function () {
            /** @var Blueprint|BlueprintMacros $this */
            $this->uuid('id')->primary();
            $this->timestamps();
            return $this;
        });

        // Product table fields
        Blueprint::macro('productTableFields', function () {
            /** @var Blueprint|BlueprintMacros $this */
            $this->shopEntityFields();
            $this->uuid('product_uuid')->unique();
            $this->string('name');
            $this->string('product_handle')->unique();
            $this->decimal('product_price', 10);
            return $this;
        });

        // Variant table fields
        Blueprint::macro('variantTableFields', function () {
            /** @var Blueprint|BlueprintMacros $this */
            $this->shopEntityFields();
            $this->uuid('product_id');
            $this->uuid('product_uuid')->index();
            $this->uuid('variant_uuid')->unique();
            $this->decimal('variant_price', 10);
            $this->string('variant_handle')->unique();

            return $this;
        });

        Blueprint::macro('addVariantForeignKey', function (string|array $columns, string $on, $fk = null) {
            /** @var Blueprint|BlueprintMacros $this */

            $this->foreign($columns, $fk)
                ->references('id')
                ->on($on)
                ->onDelete('cascade');

            return $this;
        });

        // Image table fields (generic)
        Blueprint::macro('shopImageFields', function (string $entity) {
            /** @var Blueprint|BlueprintMacros $this */
            $this->shopEntityFields();
            $this->uuid("{$entity}_id");
            $this->uuid("{$entity}_uuid");
            $this->string('url');
            return $this;
        });

        Blueprint::macro('productImageTableFields',  function () {
            /** @var Blueprint|BlueprintMacros $this */
           $this->shopImageFields('product');

           return $this;
        });

        Blueprint::macro('addProductImageForeignKey', function (string|array $columns, string $on, $fk = null) {
            /** @var Blueprint|BlueprintMacros $this */

            $this->foreign($columns, $fk)
                ->references('id')
                ->on($on)
                ->onDelete('cascade');

            return $this;
        });

        Blueprint::macro('variantImageTableFields', function () {
            /** @var Blueprint|BlueprintMacros $this */
            $this->shopImageFields('variant');

            return $this;
        });

        Blueprint::macro('addVariantImageForeignKey', function (string|array $columns, string $on, $fk = null) {
            /** @var Blueprint|BlueprintMacros $this */

            $this->foreign($columns, $fk)
                ->references('id')
                ->on($on)
                ->onDelete('cascade');

            return $this;
        });
    }
}
