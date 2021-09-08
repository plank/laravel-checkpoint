<?php

namespace Plank\Checkpoint\Models;

use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
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
 * @method static Builder|Checkpoint newModelQuery()
 * @method static Builder|Checkpoint newQuery()
 * @method static Builder|Checkpoint query()
 * @method static Builder|Checkpoint whereId($value)
 * @method static Builder|Checkpoint whereTitle($value)
 * @method static Builder|Checkpoint whereCheckpointDate($value)
 * @method static Builder|Checkpoint whereCreatedAt($value)
 * @method static Builder|Checkpoint whereUpdatedAt($value)
 * @method static Builder|Checkpoint closestTo($moment, $operator = '<=')
 * @method static Builder|Checkpoint newerThan($moment, $strict = true)
 * @method static Builder|Checkpoint olderThan($moment, $strict = true)
 * @method static Builder|Checkpoint newerThanEquals($moment)
 * @method static Builder|Checkpoint olderThanEquals($moment)
 * @mixin \Eloquent
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
     * Set the checkpoint that is active while creating content
     *
     * @var self
     */
    public static self $active;

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
    protected static ?CheckpointStore $store = null;

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
     * Get the store responsible for storing and retrieving the active checkpoint for each request
     *
     * @return CheckpointStore
     */
    public static function getStore(): CheckpointStore
    {
        if (static::$store === null) {
            /** @var CheckpointStore $storeClass */
            $storeClass = config('checkpoint.store');

            /** @var CheckpointStore $store */
            $store = new $storeClass;

            static::$store = $store;
        }

        return static::$store;
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
     * Get the timeline the checkpoint belongs to
     *
     * @return BelongsTo 
     */
    public function timeline(): BelongsTo
    {
        return $this->belongsTo(Timeline::class, static::TIMELINE_ID);
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
        return static::olderThan($this)->first();
    }

    /**
     * Return the checkpoint right after this one
     *
     * @return Checkpoint|Model|null
     */
    public function next()
    {
        return static::newerThan($this)->first();
    }

    /**
     * Retrieve all revision intermediaries associated with this checkpoint
     *
     * @return HasMany
     */
    public function revisions(): HasMany
    {
        /** @var Revision|string $revisionClass */ 
        $revisionClass = config('checkpoint.models.revision');

        return $this->hasMany($revisionClass, $revisionClass::CHECKPOINT_ID);
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
        /** @var string $revisionClass */
        $revisionClass = config('checkpoint.models.revision');

        return $this->morphedByMany($type, 'revisionable', 'revisions', 'checkpoint_id')
            ->withPivot('metadata', 'previous_revision_id', 'original_revisionable_id')->withTimestamps()
            ->using($revisionClass);
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

    /**
     * Apply a scope to filter for checkpoints closest to the one provided based on an operator
     * when less than, orders by checkpoint_date desc
     * when greater than, orders checkpoint_date asc
     *
     * @param  Builder  $query
     * @param  Checkpoint|\Illuminate\Support\Carbon|string  $moment
     * @param  string  $operator
     * @return Builder
     */
    public function scopeClosestTo(Builder $query, $moment, $operator = '<=')
    {
        $column = $this->getCheckpointDateColumn();
        if ($moment instanceof static) {
            $moment = $moment->getCheckpointDate();
        }
        $query->where($column, $operator, $moment);
        if ($operator === '<' || $operator === '<=') {
            $query->latest($column);
        } elseif ($operator === '>' || $operator === '>=') {
            $query->oldest($column);
        }
        return $query;
    }

    /**
     * Apply a scope to filter for checkpoints older than the one provided ordered by checkpoint date desc
     *
     * @param  Builder  $query
     * @param  Checkpoint|\Illuminate\Support\Carbon|string  $moment
     * @param  bool  $strict
     * @return Builder
     */
    public function scopeOlderThan(Builder $query, $moment, $strict = true)
    {
        return $query->closestTo($moment, $strict ? '<' : '<=');
    }

    /**
     * Apply a scope to filter for checkpoints older or equal to the one provided ordered by checkpoint date desc
     *
     * @param  Builder  $query
     * @param  Checkpoint|\Illuminate\Support\Carbon|string  $moment
     * @return Builder
     */
    public function scopeOlderThanEquals(Builder $query, $moment)
    {
        return $query->olderThan($moment, false);
    }

    /**
     * Apply a scope to filter for checkpoints newer than the one provided ordered by checkpoint date asc
     *
     * @param  Builder  $query
     * @param  Checkpoint|\Illuminate\Support\Carbon|string  $moment
     * @param  bool  $strict
     * @return Builder
     */
    public function scopeNewerThan(Builder $query, $moment, $strict = true)
    {
        return $query->closestTo($moment, $strict ? '>' : '>=');
    }

    /**
     * Apply a scope to filter for checkpoints newer or equal to the one provided ordered by checkpoint date asc
     *
     * @param  Builder  $query
     * @param  Checkpoint|\Illuminate\Support\Carbon|string  $moment
     * @return Builder
     */
    public function scopeNewerThanEquals(Builder $query, $moment)
    {
        return $query->newerThan($moment, false);
    }
}
