<?php

namespace Plank\Checkpoint\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphPivot;
use Plank\Checkpoint\Builders\RevisionBuilder;

/**
 * @property int $id
 * @property string $revisionable_type
 * @property int $revisionable_id
 * @property int $original_revisionable_id
 * @property int|null $previous_revision_id
 * @property int|null $checkpoint_id
 * @property mixed|null $metadata
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent $revisionable
 * @property-read \Illuminate\Database\Eloquent\Model|\Eloquent $initialRevisionable
 * @property-read \Illuminate\Database\Eloquent\Collection|Revision[] $allRevisions
 * @property-read int|null $all_revisions_count
 * @property-read \Illuminate\Database\Eloquent\Collection|Revision[] $otherRevisions
 * @property-read int|null $other_revisions_count
 * @property-read Revision|null $newest
 * @property-read Revision|null $next
 * @property-read Revision|null $previous
 * @property-read Checkpoint|null $checkpoint
 * @method static RevisionBuilder|Revision newModelQuery()
 * @method static RevisionBuilder|Revision newQuery()
 * @method static RevisionBuilder|Revision query()
 * @method static RevisionBuilder|Revision whereId($value)
 * @method static RevisionBuilder|Revision whereType($value)
 * @method static RevisionBuilder|Revision whereRevisionableId($value)
 * @method static RevisionBuilder|Revision whereRevisionableType($value)
 * @method static RevisionBuilder|Revision whereOriginalRevisionableId($value)
 * @method static RevisionBuilder|Revision wherePreviousRevisionId($value)
 * @method static RevisionBuilder|Revision whereCheckpointId($value)
 * @method static RevisionBuilder|Revision whereTimelineId($value)
 * @method static RevisionBuilder|Revision whereMetadata($value)
 * @method static RevisionBuilder|Revision whereCreatedAt($value)
 * @method static RevisionBuilder|Revision whereUpdatedAt($value)
 * @mixin RevisionBuilder
 * @mixin Model
 */
class Revision extends MorphPivot
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'revisions';

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
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = true;

    /**
     * The name of the "updated at" column.
     *
     * @var string
     */
    const UPDATED_AT = 'updated_at';

    /**
     * The name of the "checkpoint id" column.
     *
     * @var string
     */
    const CHECKPOINT_ID = 'checkpoint_id';

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
    protected $guarded = [
        'id',
        'metadata'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'metadata' => 'array'
    ];

    protected static function boot()
    {
        static::deleting(function (self $revision) {
            $next = $revision->next;
            // if newer revision exists, point its previous_revision_id to the previous revision of this item
            if ($next !== null) {
                $next->previous_revision_id = $revision->previous_revision_id;
                $next->save();
            } elseif ($revision->previous_revision_id !== null) {
                // if no newer revision exists, update the latest flag on the previous revision
                $revision->previous()->update(['latest' => true]);
            }

        });
        parent::boot();
    }

    /**
     * Create a new Eloquent query builder for the model.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return RevisionBuilder|static
     */
    public function newEloquentBuilder($query)
    {
        return new RevisionBuilder($query);
    }

    /**
     * Get the name of the "checkpoint id" column.
     *
     * @return string
     */
    public function getCheckpointKeyName()
    {
        return static::CHECKPOINT_ID;
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
     * Retrieve the revisioned model associated with this entry
     *
     * @return MorphTo
     */
    public function revisionable(): MorphTo
    {
        return $this->morphTo('revisionable')->withoutGlobalScopes();
    }

    /**
     * Retrieve the original model in this sequence
     *
     * @return MorphTo
     */
    public function initialRevisionable(): MorphTo
    {
        return $this->morphTo(
            'revisionable',
            'revisionable_type',
            'original_revisionable_id'
        )->withoutGlobalScopes();
    }

    /**
     * Retrieve the original model in this sequence
     *
     * @return MorphTo
     */
    public function originalRevisionable(): MorphTo
    {
        return $this->initialRevisionable();
    }

    /**
     * Return the associated timeline to this revision - should match the on checkpoint, if set
     *
     * @return BelongsTo
     */
    public function timeline(): BelongsTo
    {
        return $this->belongsTo(get_class(app(Timeline::class)), $this->getTimelineKeyName());
    }

    /**
     * Return the associated checkpoint/release to this revision
     *
     * @return BelongsTo
     */
    public function checkpoint(): BelongsTo
    {
        return $this->belongsTo(get_class(app(Checkpoint::class)), $this->getCheckpointKeyName());
    }

    /**
     * Return the revision made right before this one
     *
     * @return BelongsTo
     */
    public function previous(): BelongsTo
    {
        return $this->belongsTo(static::class, 'previous_revision_id', $this->getKeyName());
    }

    /**
     * Return the revision that follows this one
     *
     * @return HasOne
     */
    public function next(): HasOne
    {
        return $this->hasOne(static::class, 'previous_revision_id', $this->getKeyName());
    }

    /**
     * Return latest revision
     *
     * @return HasOne
     */
    public function newest(): HasOne
    {
        return $this->hasOne(static::class, 'revisionable_type', 'revisionable_type')
            ->where('original_revisionable_id', $this->original_revisionable_id)
            ->where('latest', true)->latest();
    }

    /**
     * Returns true if this is the most current revision for an item
     *
     * @return bool
     */
    public function isLatest(): bool
    {
        return $this->next()->doesntExist();
    }

    /**
     * Returns true if this is the first revision for an item
     *
     * @return bool
     */
    public function isNew()
    {
        return $this->previous_revision_id === null;
    }

    /**
     * Returns true if item is new for the given bulletin
     *
     * @param  Checkpoint  $moment
     * @return bool
     */
    public function isNewAt(Checkpoint $moment): bool
    {
        if ($this->checkpoint()->exists()) {
            return $this->isNew() && $moment->is($this->checkpoint);
        }

        return $this->isNew();
    }

    /**
     * Returns true if this is revision is updated at the given bulletin
     *
     * @param  Checkpoint  $moment
     * @return bool
     */
    public function isUpdatedAt(Checkpoint $moment): bool
    {
        return !$this->isNew() && ($this->checkpoint()->doesntExist() || $moment->is($this->checkpoint));
    }

    /**
     * Return all the revisions that share the same item
     *
     * @return HasMany
     */
    public function allRevisions(): HasMany
    {
        return $this->hasMany(static::class, 'revisionable_type', 'revisionable_type')
            ->where('original_revisionable_id', $this->original_revisionable_id);
    }

    /**
     * Return all the revisions sibling that share the same item except the current one
     *
     * @return HasMany
     */
    public function otherRevisions(): HasMany
    {
        return $this->allRevisions()->whereKeyNot($this->getKey());
    }
}
