<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Post>
 */
class PostFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $customTags = ['laravel', 'vue', 'mongodb', 'aws', 'nuxt', 'cybersecurity', 'php', 'devops', 'backend', 'cloud computing', 'mysql'];
        $tags = fake()->randomElements($customTags, rand(2, 5));

        $content = fake()->paragraph();

        foreach ($tags as $tag) {
            $words = explode(" ", $content);
            $randomPosition = rand(0, count($words) - 1);
            array_splice($words, $randomPosition, 0, "#$tag");
            $content = implode(' ', $words);
        }

        return [
            "content" => $content,
            "tags" => $tags,
            "user_id" => fake()->numberBetween(1, 10)
        ];
    }
}
