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
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained('usim_units')->cascadeOnDelete();
            $table->string('title');
            $table->text('content')->nullable();
            $table->string('type')->default('text'); // text, image, video
            $table->string('media_url')->nullable();
            $table->string('media_mime')->nullable();
            $table->string('status')->default('draft'); // draft, pending, approved, rejected, expired
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->unsignedInteger('display_duration_sec')->default(10);
            $table->boolean('is_public')->default(false);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();

            // Indexes for high performance querying on Kiosks and moderation lists
            $table->index('status');
            $table->index('unit_id');
            $table->index('is_public');
            $table->index(['status', 'starts_at', 'ends_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};

