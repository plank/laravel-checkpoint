<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use Plank\Checkpoint\Tests\Support\{Comment,Post};
use Faker\Generator as Faker;

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
        'content' => $faker->paragraph,
        'post_id' => Post::all()->random()->id,
    ];
});
