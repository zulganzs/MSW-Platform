<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            // ponytail: nullable user_id — anonymous reports store NULL; user still authed but name hidden
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('description');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->enum('status', ['submitted', 'assigned', 'in_progress', 'completed'])->default('submitted');
            $table->enum('visibility', ['public', 'private', 'anonymous'])->default('public');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
