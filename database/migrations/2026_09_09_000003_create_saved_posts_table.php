<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration creating the relational `saved_posts` table in MySQL.
 * Stores bookmarks linking user accounts to MongoDB post documents.
 * Enables persistent saves and rapid querying of bookmarked threads.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('saved_posts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('post_id', 64);
            $table->timestamps();

            $table->unique(['user_id', 'post_id']);
            $table->index('user_id');
            $table->index('post_id');
            $table->index(['user_id', 'created_at']);

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('saved_posts');
    }
};
