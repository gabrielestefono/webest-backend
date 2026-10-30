<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by_id')->constrained('users')->restrictOnDelete();
            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('file_size');
            $table->string('disk')->default('private');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_attachments');
    }
};

