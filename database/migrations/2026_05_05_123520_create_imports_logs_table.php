<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('import_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('import_id');
            $table->string('transaction_id')->nullable();
            $table->text('error_message');
            $table->timestamps();

            $table->foreign('import_id')
                ->references('id')
                ->on('imports')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('import_logs');
    }
};
