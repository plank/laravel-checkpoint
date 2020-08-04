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
    protected $signature = 'checkpoint:start {class?}';

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
        if ($class = $this->argument('class')) {
            $records = $class::withoutGlobalScopes()->chunk(100, function ($results) {
               foreach ($results as $item) {
                   $item->startRevisioning();
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
