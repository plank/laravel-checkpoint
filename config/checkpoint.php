<?php

return [

    /*
     * When true, checkpoint will automatically hook its observer to the
     * revisionable model events in order to create revisions when needed
     */
    'enabled' => env('CHECKPOINT_ENABLED', true),

    /*
     * When true, checkpoint will automatically apply the global revision scope
     * onto revisionables for filtering out relevant content based on time
     *
     * ***warning***
     * disabling after using the package will result in query results containing
     * duplicate items as they won't automatically be filtered by time
     */
    'apply_global_scope' => env('REVISIONS_GLOBAL_SCOPE', env('CHECKPOINT_ENABLED', true)),

    /*
     * Should checkpoint run its default migrations
     */
    'run_migrations' => env('RUN_CHECKPOINT_MIGRATIONS', true),

    'models' => [

        /*
         * When using the "HasRevisions" trait from this package, we need to know which model
         * should be used to retrieve your revisions. To extend or replace this functionality,
         * change the value below with your full "revision model" class name.
         */
        'revision' => Plank\Checkpoint\Models\Revision::class,

        /*
         * When using the "HasRevisions" trait from this package, we need to know which model
         * should be used to retrieve your checkpoints. To extend or replace this functionality,
         * change the value below with your full "checkpoint model" class name.
         */
        'checkpoint' => Plank\Checkpoint\Models\Checkpoint::class,

    ],

];
