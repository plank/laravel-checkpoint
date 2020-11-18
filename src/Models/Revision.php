<?php

namespace Plank\Checkpoint\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphPivot;

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
     * Prevent Eloquent from overriding uuid with `lastInsertId`.
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

    /**
     * Get the name of the "checkpoint id" column.
     *
     * @return string
     */
    public function getCheckpointIdColumn()
    {
        return static::CHECKPOINT_ID;
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
     * Return the associated checkpoint/release to this revision
     *
     * @return BelongsTo
     */
    public function checkpoint(): BelongsTo
    {
        $model = config('checkpoint.checkpoint_model', Checkpoint::class);
        return $this->belongsTo($model, $this->getCheckpointIdColumn());
    }

    /**
     * Return the revision made right before this one
     *
     * @return BelongsTo
     */
    public function previous(): BelongsTo
    {
        return $this->belongsTo(static::class, 'previous_revision_id', $this->primaryKey);
    }

    /**
     * Return the revision that follows this one
     *
     * @return BelongsTo
     */
    public function next(): HasOne
    {
        return $this->hasOne(static::class, 'previous_revision_id', $this->primaryKey);
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
    public function isNew() {
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
     * Return all the revisions sibling that share the same item
     *
     * @return
     */
    public function otherRevisions(): HasMany
    {
        return $this->allRevisions()->where('id', '!=', $this->getKey());
    }

    /**
     * @param  Builder  $q
     * @param  Checkpoint|Carbon|string|null  $until
     * @param  Checkpoint|Carbon|string|null  $since
     * @return Builder
     */
    public function scopeLatestIds(Builder $q, $until = null, $since = null)
    {
        $q->withoutGlobalScopes()->selectRaw("max({$this->getKeyName()}) as closest")
            ->groupBy(['original_revisionable_id', 'revisionable_type'])->orderByDesc('previous_revision_id');

        $checkpoint = config('checkpoint.checkpoint_model', Checkpoint::class);

        if ($until instanceof $checkpoint) {
            // where in given checkpoint or one of the previous ones
            $q->whereIn($this->getCheckpointIdColumn(), $checkpoint::olderThanEquals($until)->select('id'));
        } elseif ($until !== null) {
            $q->where($this->getQualifiedCreatedAtColumn(), '<=', $until);
        }

        if ($since instanceof $checkpoint) {
            // where in one of the newer checkpoints than given
            $q->whereIn($this->getCheckpointIdColumn(), $checkpoint::newerThan($since)->select('id'));
        } elseif ($since !== null) {
            $q->where($this->getQualifiedCreatedAtColumn(), '>', $since);
        }

        return $q;
    }

    /**
     * @param  Builder  $q
     * @param  Model|string|null  $type
     * @return Builder
     */
    public function scopeWhereType(Builder $q, $type = null)
    {
        if (is_string($type)) {
            $q->where('revisionable_type', $type);
        } elseif ($type instanceof Model) {
            $q->where('revisionable_type', get_class($type));
        }
        return $q;
    }
}
