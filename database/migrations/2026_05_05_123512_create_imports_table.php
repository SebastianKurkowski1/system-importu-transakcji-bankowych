<?php

use App\Enums\ImportStatus;
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
        Schema::create('imports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('file_name');
            $table->unsignedInteger('total_records')->default(0);
            $table->unsignedInteger('successful_records')->default(0);
            $table->unsignedInteger('failed_records')->default(0);
            $table->enum('status', ImportStatus::values());
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('imports');
    }
};
