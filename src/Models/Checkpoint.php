<?php
namespace Plank\Checkpoint\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;

class Checkpoint extends Model
{

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'checkpoints';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * The "type" of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'int';

    /**
     * Prevent Eloquent from overriding uuid with `lastInsertId`.
     *
     * @var bool
     */
    public $incrementing = true;


    /**
     * The name of the "updated at" column.
     *
     * @var string
     */
    const UPDATED_AT = 'updated_at';

    /**
     * The name of the "checkpoint date" column
     *
     * @var string
     */
    const CHECKPOINT_DATE = 'checkpoint_date';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * Get the name of the "checkpoint date" column.
     *
     * @return string
     */
    public function getCheckpointDateColumn()
    {
        return static::CHECKPOINT_DATE;
    }

    /**
     * Retrieve all revision intermediaries associated with this checkpoint
     *
     * @return HasMany
     */
    public function revisions(): HasMany
    {
        $model = config('checkpoint.revision_model', Revision::class);
        return $this->hasMany($model, 'checkpoint_id');
    }

    /**
     * Retrieve all models of a specific type directly associated with this checkpoint
     * more efficient than models since it performs only a single query
     *
     * @param string $type class name of the models you want to fetch
     * @return MorphToMany
     */
    public function modelsOf($type): MorphToMany
    {
        $rev = config('checkpoint.revision_model', Revision::class);
        return $this->morphedByMany($type, 'revisionable', 'revisions', 'checkpoint_id')
            ->withPivot('metadata', 'previous_revision_id', 'original_revisionable_id')->withTimestamps()
            ->using($rev);
    }

    /**
     * Retrieve all models directly associated with this checkpoint
     * More expensive than just calling
     *
     * @return Collection
     */
    public function models(): Collection
    {
        return $this->revisions()->with('revisionable')->get()->pluck('revisionable');
    }
}
