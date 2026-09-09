<?php

namespace Database\Factories;

use App\Models\PostMetaData;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PostMetaData>
 */
class PostMetaDataFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'post_id' => 1,
        ];
    }
}
