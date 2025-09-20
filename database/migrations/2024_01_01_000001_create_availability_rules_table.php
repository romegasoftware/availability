<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('availability.table'), function (Blueprint $table): void {
            $table->id();
            $table->morphs('subject');
            $table->string('type');
            $table->json('config')->nullable();
            $table->string('effect', 16);
            $table->integer('priority')->default(0);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->index(['subject_type', 'subject_id', 'enabled']);
            $table->index(['type', 'enabled']);
            $table->index(['priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('availability.table'));
    }
};
