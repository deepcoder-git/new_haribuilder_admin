<?php

use App\Utility\Enums\OrderStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Updates the orders.status ENUM column to replace 'delivery' with 'delivered'
     * and migrates existing data.
     */
    public function up(): void
    {
        // Step 1: First, modify the ENUM column to include 'delivered' (keeping 'delivery' temporarily)
        // This allows us to update the data without errors
        $enumValuesWithBoth = ['pending', 'approved', 'in_transit', 'delivery', 'delivered', 'rejected', 'cancelled', 'outfordelivery'];
        $enumStringWithBoth = "'" . implode("','", $enumValuesWithBoth) . "'";
        DB::statement("ALTER TABLE `orders` MODIFY COLUMN `status` ENUM({$enumStringWithBoth}) DEFAULT 'pending'");

        // Step 2: Update existing data from 'delivery' to 'delivered' in status column
        DB::table('orders')
            ->where('status', 'delivery')
            ->update(['status' => 'delivered']);

        // Step 3: Update 'delivery' values in product_status JSON field
        // Get all orders with product_status containing 'delivery'
        $orders = DB::table('orders')
            ->whereNotNull('product_status')
            ->get();

        foreach ($orders as $order) {
            $productStatus = json_decode($order->product_status, true);
            $updated = false;

            if (is_array($productStatus)) {
                // Handle nested arrays (like LPO supplier-wise statuses)
                foreach ($productStatus as $key => $value) {
                    if (is_array($value)) {
                        // For LPO supplier-wise statuses
                        foreach ($value as $supplierId => $status) {
                            if ($status === 'delivery') {
                                $productStatus[$key][$supplierId] = 'delivered';
                                $updated = true;
                            }
                        }
                    } elseif ($value === 'delivery') {
                        $productStatus[$key] = 'delivered';
                        $updated = true;
                    }
                }

                if ($updated) {
                    DB::table('orders')
                        ->where('id', $order->id)
                        ->update(['product_status' => json_encode($productStatus)]);
                }
            }
        }

        // Step 4: Now remove 'delivery' from the ENUM column definition (final cleanup)
        // MySQL requires raw SQL to modify ENUM values
        $enumValues = array_map(
            static fn (OrderStatusEnum $e) => $e->value,
            OrderStatusEnum::cases()
        );

        $enumString = "'" . implode("','", $enumValues) . "'";
        
        DB::statement("ALTER TABLE `orders` MODIFY COLUMN `status` ENUM({$enumString}) DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     * 
     * Reverts 'delivered' back to 'delivery' in the ENUM and data.
     */
    public function down(): void
    {
        // Step 1: First, modify the ENUM column to include 'delivery' (keeping 'delivered' temporarily)
        $enumValuesWithBoth = ['pending', 'approved', 'in_transit', 'delivery', 'delivered', 'rejected', 'cancelled', 'outfordelivery'];
        $enumStringWithBoth = "'" . implode("','", $enumValuesWithBoth) . "'";
        DB::statement("ALTER TABLE `orders` MODIFY COLUMN `status` ENUM({$enumStringWithBoth}) DEFAULT 'pending'");

        // Step 2: Update existing data from 'delivered' to 'delivery' in status column
        DB::table('orders')
            ->where('status', 'delivered')
            ->update(['status' => 'delivery']);

        // Step 3: Update 'delivered' values in product_status JSON field back to 'delivery'
        $orders = DB::table('orders')
            ->whereNotNull('product_status')
            ->get();

        foreach ($orders as $order) {
            $productStatus = json_decode($order->product_status, true);
            $updated = false;

            if (is_array($productStatus)) {
                // Handle nested arrays (like LPO supplier-wise statuses)
                foreach ($productStatus as $key => $value) {
                    if (is_array($value)) {
                        // For LPO supplier-wise statuses
                        foreach ($value as $supplierId => $status) {
                            if ($status === 'delivered') {
                                $productStatus[$key][$supplierId] = 'delivery';
                                $updated = true;
                            }
                        }
                    } elseif ($value === 'delivered') {
                        $productStatus[$key] = 'delivery';
                        $updated = true;
                    }
                }

                if ($updated) {
                    DB::table('orders')
                        ->where('id', $order->id)
                        ->update(['product_status' => json_encode($productStatus)]);
                }
            }
        }

        // Step 4: Revert the ENUM column definition (remove 'delivered', keep 'delivery')
        $enumValues = ['pending', 'approved', 'in_transit', 'delivery', 'rejected', 'cancelled', 'outfordelivery'];
        $enumString = "'" . implode("','", $enumValues) . "'";
        
        DB::statement("ALTER TABLE `orders` MODIFY COLUMN `status` ENUM({$enumString}) DEFAULT 'pending'");
    }
};
