<?php

/*
 * You can place your custom package configuration in here.
 */
return [

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
    | To extend or replace this functionality, change the value below with your full "version model" FQN.
    |
    */
    'release_model' => \Plank\Versionable\Models\Version::class,


];
