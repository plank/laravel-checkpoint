<?php


namespace Plank\Checkpoint\Commands;

use Illuminate\Console\Command;

class StartRevisioning extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'checkpoint:start
                            {class? : a specified class to start revisioning on}
                            {--on= : The checkpoint ID that all revisions should be attached to}
                            {--C|with-checkpoint : also create a starting checkpoint to attach all revisions to}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create revisions for each instance of revisionable models';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $checkpoint = null;
        if ($this->option('with-checkpoint')) {
            $checkpointClass = config('checkpoint.checkpoint_model');
            $checkpoint = $checkpointClass::first() ?? new $checkpointClass();
            $checkpoint->save();
            $checkpoint->refresh();
        }

        if ($class = $this->argument('class')) {
            $records = $class::withoutGlobalScopes()->chunk(100, function ($results) use ($checkpoint, &$timeDelta) {
               foreach ($results as $item) {
                   $item->updateOrCreateRevision();
                   $revision = $item->revision;
                   // TODO: shouldn't be required for global query anymore.
                   $revision->created_at = $item->freshRevisionCreatedAt();
                   $revision->checkpoint()->associate($checkpoint);
                   $revision->save();
               }
            });
        } else {
            // TODO: make this discover revisionable models, and boot them one by one.
            $this->error('Please pass in the FQCN of a revisionable model.');
            return 1;
        }
            return 0;
    }
}
