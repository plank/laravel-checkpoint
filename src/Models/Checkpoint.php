<?php

namespace Plank\Checkpoint\Models;

use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Plank\Checkpoint\Builders\CheckpointBuilder;
use Plank\Checkpoint\Contracts\CheckpointStore;
use Plank\Checkpoint\Observers\CheckpointObserver;

/**
 * @property int $id
 * @property string $title
 * @property string $checkpoint_date
 * @property-read null|Timeline $timeline
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Revision[] $revisions
 * @property-read int|null $revisions_count
 * @method static CheckpointBuilder|Checkpoint newModelQuery()
 * @method static CheckpointBuilder|Checkpoint newQuery()
 * @method static CheckpointBuilder|Checkpoint query()
 * @method static CheckpointBuilder|Checkpoint whereId($value)
 * @method static CheckpointBuilder|Checkpoint whereTitle($value)
 * @method static CheckpointBuilder|Checkpoint whereCheckpointDate($value)
 * @method static CheckpointBuilder|Checkpoint whereCreatedAt($value)
 * @method static CheckpointBuilder|Checkpoint whereUpdatedAt($value)
 * @mixin CheckpointBuilder
 * @mixin Model
 */
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
     * Indicates if the IDs are auto-incrementing.
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
     * The name of the "timeline id" column.
     *
     * @var string
     */
    const TIMELINE_ID = 'timeline_id';

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [self::CHECKPOINT_DATE];

    /**
     * The class responsible for storing and retrieving the active checkpoint for each request
     *
     * @var null|CheckpointStore
     */
    protected static $store = null;

    /**
     * The "booting" method of the model.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        static::observe(CheckpointObserver::class);
    }

    /**
     * Create a new Eloquent query builder for the model.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return CheckpointBuilder|static
     */
    public function newEloquentBuilder($query)
    {
        return new CheckpointBuilder($query);
    }

    /**
     * Get the store responsible for storing and retrieving the active checkpoint for each request
     *
     * @return CheckpointStore
     */
    public static function getStore(): CheckpointStore
    {
        return app(CheckpointStore::class);
    }

    /**
     * Set the active checkpoint we are viewing/updating
     *
     * @param Checkpoint $checkpoint
     * @return void
     */
    public static function setActive(self $checkpoint): void
    {
        static::getStore()->store($checkpoint);
    }

    /**
     * Set the active checkpoint we are viewing/updating
     *
     * @return void
     */
    public static function clearActive(): void
    {
        static::getStore()->clear();
    }

    /**
     * Get the active checkpoint we are working on
     *
     * @return null|Checkpoint
     */
    public static function active(): ?Checkpoint
    {
        return static::getStore()->retrieve();
    }

    /**
     * Get the name of the "timeline id" column.
     *
     * @return string
     */
    public function getTimelineKeyName()
    {
        return static::TIMELINE_ID;
    }

    /**
     * Get the "timeline" key.
     *
     * @return \Illuminate\Support\Carbon|string
     */
    public function getTimelineKey()
    {
        return $this->{$this->getTimelineKeyName()};
    }


    /**
     * Get the name of the "checkpoint date" column.
     *
     * @return string
     */
    public function getCheckpointDateColumn(): string
    {
        return static::CHECKPOINT_DATE;
    }

    /**
     * Get the "checkpoint date" field.
     *
     * @return \Illuminate\Support\Carbon|string
     */
    public function getCheckpointDate()
    {
        return $this->{$this->getCheckpointDateColumn()};
    }

    /**
     * Return current checkpoint at this moment in time
     *
     * @return Checkpoint|Model|null
     */
    public static function current() {
        return static::olderThan(now())->first();
    }

    /**
     * Return the checkpoint right before this one
     * note: calculated via checkpoint date, ids are sequential but then you would be locked in to use auto-increments
     *
     * @return Checkpoint|Model|null
     */
    public function previous()
    {
        return $this->olderThan($this)->first();
    }

    /**
     * Return the checkpoint right after this one
     *
     * @return Checkpoint|Model|null
     */
    public function next()
    {
        return $this->newerThan($this)->first();
    }

    /**
     * Get the timeline the checkpoint belongs to
     *
     * @return BelongsTo
     */
    public function timeline(): BelongsTo
    {
        return $this->belongsTo(get_class(app(Timeline::class)), $this->getTimelineKeyName());
    }

    /**
     * Retrieve all revision intermediaries associated with this checkpoint
     *
     * @return HasMany
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(get_class(app(Revision::class)), app(Revision::class)->getCheckpointKeyName());
    }

    /**
     * Retrieve all models of a specific type directly associated with this checkpoint
     * more efficient than models since it performs only a single query
     *
     * @param  string  $type  class name of the models you want to fetch
     * @return MorphToMany
     */
    public function modelsOf(string $type): MorphToMany
    {
        return $this->morphedByMany($type, 'revisionable', 'revisions', 'checkpoint_id')
            ->withPivot('metadata', 'previous_revision_id', 'original_revisionable_id')->withTimestamps()
            ->using(get_class(app(Revision::class)));
    }

    /**
     * Retrieve all models directly associated with this checkpoint
     * More expensive than just calling modelsOf() with specific class / type
     *
     * @return Collection
     */
    public function models(): Collection
    {
        return $this->revisions()->with('revisionable')->get()->pluck('revisionable');
    }
}
