<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
use Salvon\Bundle\Media\Enum\{MediaProvider, MediaType};

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('media', static function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->smallInteger('provider')->default(MediaProvider::LOCAL->value);
            $table->smallInteger('media_type')->default(MediaType::IMAGE);
            $table->mediumText('location');
            $table->string('name')->nullable();
            $table->string('mime_type')->nullable();
            $table->string('extension')->nullable();
            $table->json('metadata')->nullable();
            $table->char('checksum', 32);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['checksum']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
