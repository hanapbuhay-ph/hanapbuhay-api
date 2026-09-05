<?php

namespace Database\Factories;

use App\Models\JobPost;
use Illuminate\Database\Eloquent\Factories\Factory;

class JobPostImageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'job_post_id' => JobPost::factory(),
            'image_path' => 'job_posts/1/'.fake()->uuid().'.jpg',
            'thumbnail_path' => 'job_posts/1/'.fake()->uuid().'_thumb.jpg',
            'display_order' => 0,
        ];
    }
}
