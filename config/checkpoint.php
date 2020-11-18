<?php

/*
 * You can place your custom package configuration in here.
 */
return [

    /*
    | Should Checkpoint run the default migrations
    |
    */
    'run_migrations' => env('RUN_CHECKPOINT_MIGRATIONS', true),

    /*
    |
    | The full namespace to your User model class.
    |
    | If your application doesn't have a user class, the value should be "NULL".
    |
    */
    'user_model' => '\App\User',

    /*
    |
    | Concrete implementation for the "version model".
    | To extend or replace this functionality, change the value below with your full "version model" FQCN.
    |
    */
    'checkpoint_model' => \Plank\Checkpoint\Models\Checkpoint::class,

    /*
    |
    | Concrete implementation for the "version model".
    | To extend or replace this functionality, change the value below with your full "version model" FQCN.
    |
    */
    'revision_model' => \Plank\Checkpoint\Models\Revision::class,


];
