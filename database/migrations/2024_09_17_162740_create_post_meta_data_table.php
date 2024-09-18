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
        Schema::create('post_meta_data', function (Blueprint $table) {
            $table->id();
            $table->uuid('post_id'); // To relate this data with the post in MongoDB
            $table->unsignedBigInteger('user_id');  // To relate it with the user in MySQL
            $table->integer('likes_count')->default(0);  // Number of likes on the post
            $table->integer('comments_count')->default(0);  // Number of comments
            $table->integer('shares_count')->default(0);  // Number of shares
            $table->string('visibility')->default('public');  // Post visibility (e.g., public, private)
            $table->string('status')->default('published');  // Post status (e.g., published, draft)
            $table->timestamp('scheduled_at')->nullable();  // For scheduling posts in the future
            $table->timestamp('expires_at')->nullable();  // When the post should expire
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('post_meta_data');
    }
};
