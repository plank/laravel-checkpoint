<?php


namespace Plank\Checkpoint\Commands;

use Illuminate\Console\Command;
use Illuminate\Container\Container;
use Illuminate\Support\Facades\File;

class StartRevisioning extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'checkpoint:start
                            {class? : specify one or more classes to start revisions on}
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
     * @return mixed
     */
    public function handle()
    {
        $checkpoint = null;
        if ($this->option('with-checkpoint')) {
            $checkpointClass = config('checkpoint.models.checkpoint');
            $checkpoint = $checkpointClass::first() ?? new $checkpointClass();
            $checkpoint->save();
            $checkpoint->refresh();
        }

        if ($class = $this->argument('class')) {
            $models = explode(',', str_replace(' ', '', $class));
        } else {
            // TODO: maybe pull base paths from composer psr-4, to support modular laravel codebases??
            $models = collect(File::allFiles(app_path()))->map(function ($item) {
                $path = $item->getRelativePathName();
                return sprintf('\%s%s', Container::getInstance()->getNamespace(),
                    str_replace('/', '\\', substr($path, 0, strrpos($path, '.'))));
            })->filter(function ($model) {
                return method_exists($model, 'bootHasRevisions');
            });
        }

        foreach ($models as $class) {
            $records = $class::withoutGlobalScopes()->chunk(100, function ($results) use ($checkpoint, &$timeDelta) {
               foreach ($results as $item) {
                   $item->startRevision();
                   $revision = $item->revision;
                   $revision->checkpoint()->associate($checkpoint);
                   $revision->save();
               }
            });
        }
    }
}
