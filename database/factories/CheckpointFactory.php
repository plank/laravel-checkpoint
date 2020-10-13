<?php

use Plank\Checkpoint\Models\Checkpoint;

$factory->define(Checkpoint::class, function (Faker\Generator $faker) {
    return [
        'title' => $faker->words(3, true),
        'checkpoint_date' => $faker->dateTimeThisMonth()
   ];
});