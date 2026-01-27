<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update products table: change store value from 'warehouse_store' to 'workshop_store'
        DB::table('products')
            ->where('store', 'warehouse_store')
            ->update(['store' => 'workshop_store']);

        // Update orders table: change product_status JSON keys from 'warehouse' to 'workshop'
        $orders = DB::table('orders')
            ->whereNotNull('product_status')
            ->get();

        foreach ($orders as $order) {
            $productStatus = json_decode($order->product_status, true);
            
            if (is_array($productStatus) && isset($productStatus['warehouse'])) {
                // Rename 'warehouse' key to 'workshop'
                $productStatus['workshop'] = $productStatus['warehouse'];
                unset($productStatus['warehouse']);
                
                // Update the order
                DB::table('orders')
                    ->where('id', $order->id)
                    ->update(['product_status' => json_encode($productStatus)]);
            }
        }

        // Update orders table: change product_rejection_notes JSON keys from 'warehouse' to 'workshop'
        $ordersWithRejectionNotes = DB::table('orders')
            ->whereNotNull('product_rejection_notes')
            ->get();

        foreach ($ordersWithRejectionNotes as $order) {
            $rejectionNotes = json_decode($order->product_rejection_notes, true);
            
            if (is_array($rejectionNotes) && isset($rejectionNotes['warehouse'])) {
                // Rename 'warehouse' key to 'workshop'
                $rejectionNotes['workshop'] = $rejectionNotes['warehouse'];
                unset($rejectionNotes['warehouse']);
                
                // Update the order
                DB::table('orders')
                    ->where('id', $order->id)
                    ->update(['product_rejection_notes' => json_encode($rejectionNotes)]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert products table: change store value from 'workshop_store' back to 'warehouse_store'
        DB::table('products')
            ->where('store', 'workshop_store')
            ->update(['store' => 'warehouse_store']);

        // Revert orders table: change product_status JSON keys from 'workshop' back to 'warehouse'
        $orders = DB::table('orders')
            ->whereNotNull('product_status')
            ->get();

        foreach ($orders as $order) {
            $productStatus = json_decode($order->product_status, true);
            
            if (is_array($productStatus) && isset($productStatus['workshop'])) {
                // Rename 'workshop' key back to 'warehouse'
                $productStatus['warehouse'] = $productStatus['workshop'];
                unset($productStatus['workshop']);
                
                // Update the order
                DB::table('orders')
                    ->where('id', $order->id)
                    ->update(['product_status' => json_encode($productStatus)]);
            }
        }

        // Revert orders table: change product_rejection_notes JSON keys from 'workshop' back to 'warehouse'
        $ordersWithRejectionNotes = DB::table('orders')
            ->whereNotNull('product_rejection_notes')
            ->get();

        foreach ($ordersWithRejectionNotes as $order) {
            $rejectionNotes = json_decode($order->product_rejection_notes, true);
            
            if (is_array($rejectionNotes) && isset($rejectionNotes['workshop'])) {
                // Rename 'workshop' key back to 'warehouse'
                $rejectionNotes['warehouse'] = $rejectionNotes['workshop'];
                unset($rejectionNotes['workshop']);
                
                // Update the order
                DB::table('orders')
                    ->where('id', $order->id)
                    ->update(['product_rejection_notes' => json_encode($rejectionNotes)]);
            }
        }
    }
};
