<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use Faker\Generator as Faker;
use Plank\Checkpoint\Tests\Support\Models\Blog\Post;
use Plank\Checkpoint\Tests\Support\Models\CMS\Block;
use Plank\Checkpoint\Tests\Support\Models\CMS\Page;
use Plank\Checkpoint\Tests\Support\Models\Servers\Cluster;
use Plank\Checkpoint\Tests\Support\Models\Servers\Environment;
use Plank\Checkpoint\Tests\Support\Models\Servers\Group;
use Plank\Checkpoint\Tests\Support\Models\Servers\Server;

$factory->define(Page::class, function (Faker $faker) {
    return [
        'template' => $faker->word . 'layout',
        'title' => $faker->words(4, true),
        'slug' => $faker->unique()->word,
        'position' => $faker->randomNumber(3),
        'parent_id' => $faker->randomElement([null, Page::inRandomOrder()->first()->id ?? null]),
    ];
});

$factory->define(Block::class, function (Faker $faker) {
    $callout = $faker->randomElement([
        Post::inRandomOrder()->first(),
        Server::inRandomOrder()->first(),
        null
    ]);
    return [
        'page_id' => Page::inRandomOrder()->first() ?? factory(Page::class),
        'calloutable_id' => $callout->id ?? null,
        'calloutable_type' => $callout === null ? null : get_class($callout),
        'template' => $callout !== null ? 'calloutlayout' : 'textlayout',
        'title' => $faker->words(4, true),
        'content' => [[
            'attributes' => [
                'callout_id' => $callout->id ?? null,
                'title' => $faker->words,
                'body' => $faker->paragraph,
            ],
            'layout' => ($callout !== null ? 'calloutlayout' : 'textlayout'),
            'key' => $faker->uuid,
        ]],
        'span' => $faker->randomElement(array_keys(Block::SPAN_OPTIONS)),
        'position' => $faker->randomNumber(3),
    ];
});

$factory->define(Group::class, function (Faker $faker) {
    return [
        'title' => $faker->words(4, true),
        'slug' => $faker->unique()->word,
        'position' => $faker->randomNumber(3),
        'parent_id' => $faker->randomElement([null, Group::inRandomOrder()->first()->id ?? null]),
    ];
});

$factory->define(Environment::class, function (Faker $faker) {
    $groups = Group::inRandomOrder()->take(2)->get();
    return [
        'title' => $faker->words(4, true),
        'slug' => $faker->unique()->word,
        'group_id' => $faker->randomElement([null, $groups->first()->id ?? null]),
        'subgroup_id' => $faker->randomElement([null, $groups->last()->id ?? null]),
    ];
});

$factory->define(Server::class, function (Faker $faker) {
    return [
        'environment_id' => Environment::inRandomOrder()->first()->id,
        'title' => $faker->words(4, true),
        'description' => $faker->paragraphs(3, true),
        'manufacturer' => $faker->company,
        'notes' => $faker->sentence,
        'finish' => $faker->boolean,
    ];
});

$factory->define(Cluster::class, function (Faker $faker) {
    return [
        'title' => $faker->words(4, true),
        'slug' => $faker->unique()->word,
        'parent_id' => $faker->randomElement([null, Cluster::inRandomOrder()->first()->id ?? null]),
    ];
});
