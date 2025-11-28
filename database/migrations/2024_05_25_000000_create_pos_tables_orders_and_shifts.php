<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_tables', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('account_id')->index();
            $table->string('name');
            $table->unsignedInteger('capacity')->default(0);
            $table->string('status')->default('available');
            $table->unsignedInteger('merged_into_table_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $table->foreign('merged_into_table_id')->references('id')->on('pos_tables')->onDelete('set null');
        });

        Schema::create('pos_shifts', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('account_id')->index();
            $table->unsignedInteger('user_id')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->decimal('opening_float', 13, 2)->default(0);
            $table->decimal('closing_float', 13, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });

        Schema::create('pos_orders', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('account_id')->index();
            $table->unsignedInteger('table_id')->nullable();
            $table->unsignedInteger('shift_id')->nullable();
            $table->string('reference')->unique();
            $table->string('status')->default('open');
            $table->unsignedInteger('covers')->default(0);
            $table->decimal('subtotal', 13, 2)->default(0);
            $table->decimal('discount_total', 13, 2)->default(0);
            $table->decimal('total', 13, 2)->default(0);
            $table->unsignedInteger('opened_by')->nullable();
            $table->unsignedInteger('closed_by')->nullable();
            $table->unsignedInteger('merged_into_order_id')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $table->foreign('table_id')->references('id')->on('pos_tables')->onDelete('set null');
            $table->foreign('shift_id')->references('id')->on('pos_shifts')->onDelete('set null');
            $table->foreign('opened_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('closed_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('merged_into_order_id')->references('id')->on('pos_orders')->onDelete('set null');
        });

        Schema::create('pos_order_items', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('order_id');
            $table->string('name');
            $table->integer('quantity');
            $table->decimal('unit_price', 13, 2);
            $table->decimal('discount_amount', 13, 2)->default(0);
            $table->decimal('total', 13, 2);
            $table->text('notes')->nullable();
            $table->unsignedInteger('voided_by')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->unsignedInteger('transferred_to_order_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('order_id')->references('id')->on('pos_orders')->onDelete('cascade');
            $table->foreign('voided_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('transferred_to_order_id')->references('id')->on('pos_orders')->onDelete('set null');
        });

        Schema::create('pos_order_adjustments', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('account_id')->index();
            $table->unsignedInteger('order_id');
            $table->unsignedInteger('order_item_id')->nullable();
            $table->string('type');
            $table->decimal('amount', 13, 2)->default(0);
            $table->unsignedInteger('user_id')->nullable();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
            $table->foreign('order_id')->references('id')->on('pos_orders')->onDelete('cascade');
            $table->foreign('order_item_id')->references('id')->on('pos_order_items')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_order_adjustments');
        Schema::dropIfExists('pos_order_items');
        Schema::dropIfExists('pos_orders');
        Schema::dropIfExists('pos_shifts');
        Schema::dropIfExists('pos_tables');
    }
};
