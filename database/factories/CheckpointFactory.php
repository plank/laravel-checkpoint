<?php

use Plank\Checkpoint\Models\Checkpoint;

/** @var \Illuminate\Database\Eloquent\Factory $factory */

$factory->define(Checkpoint::class, function (Faker\Generator $faker) {
    return [
        'title' => $faker->words(3, true),
        'checkpoint_date' => $faker->dateTimeThisMonth()
   ];
});
