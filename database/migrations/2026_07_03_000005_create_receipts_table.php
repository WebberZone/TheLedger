<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create(
            'receipts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
                // Path on the private local Flysystem disk (outside public root).
                $table->string('path');
                $table->string('original_filename')->nullable();
                $table->string('content_type')->nullable();
                $table->unsignedBigInteger('byte_size')->nullable();
                // Per-image extraction result, kept for audit.
                $table->json('ocr_json')->nullable();
                $table->timestamps();
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};
