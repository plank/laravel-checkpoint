<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use Faker\Generator as Faker;
use Plank\Checkpoint\Tests\Support\Models\Blog\Comment;
use Plank\Checkpoint\Tests\Support\Models\Blog\Post;

$factory->define(Post::class, function (Faker $faker) {
    return [
        'title' => $faker->words(4, true),
        'slug' => $faker->unique()->word,
        'excerpt' => $faker->sentence(10),
        'body' => $faker->paragraphs(5, true),
    ];
});


$factory->define(Comment::class, function (Faker $faker) {
    return [
        'body' => $faker->paragraph,
        'post_id' => Post::all()->random()->id,
    ];
});
