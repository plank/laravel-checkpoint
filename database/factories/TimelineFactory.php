<?php

use Plank\Checkpoint\Models\Timeline;

$factory->define(Timeline::class, function (Faker\Generator $faker) {
    return [
        'title' => $faker->words(3, true),
   ];
});