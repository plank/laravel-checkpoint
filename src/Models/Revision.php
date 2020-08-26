<?php
namespace Plank\Checkpoint\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphPivot;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

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
        return $this->belongsTo(get_class($this), 'previous_revision_id', $this->primaryKey);
    }

    /**
     * Return the revision that follows this one
     *
     * @return BelongsTo
     */
    public function next(): HasOne
    {
        return $this->hasOne(get_class($this), 'previous_revision_id', $this->primaryKey);
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
     * Returns true if this is the most current revision for an item
     *
     * @param  Checkpoint  $moment
     * @return bool
     */
    public function isUpdatedAt(Checkpoint $moment): bool
    {
        $previous = $moment->previous();

        if ($previous !== null && !$this->isNew() && $this->previous->checkpoint()->exists()) {
            return $previous->is($this->previous->checkpoint);
        }

        return false;
    }

    /**
     * Return all the revisions that share the same item
     *
     * @return
     */
    public function allRevisions(): HasMany
    {
        return $this->hasMany(get_class($this), 'revisionable_type', 'revisionable_type')
            ->where('original_revisionable_id', $this->original_revisionable_id);
    }

    /**
     * Return all the revisions sibling that share the same item
     *
     * @return
     */
    public function otherRevisions(): HasMany
    {
        return $this->allRevisions()->where('id', '!=', $this->id);
    }

    /**
     * @param  Builder  $q
     * @param  Checkpoint|Carbon|string|null  $until
     * @param  Checkpoint|Carbon|string|null  $since
     * @return Builder
     */
    public function scopeLatestIds(Builder $q, $until = null, $since = null)
    {
        $q->withoutGlobalScopes()->selectRaw("max({$this->getQualifiedKeyName()}) as closest")
            ->groupBy(['original_revisionable_id', 'revisionable_type'])->orderBy(DB::raw('NULL'));


        $checkpoint = config('checkpoint.checkpoint_model', Checkpoint::class);
        $checkpointDateColumn = $checkpoint::CHECKPOINT_DATE;

        if ($until instanceof $checkpoint) {
            // where in this checkpoint or one of the previous ones
            $q->whereIn(
                $this->getCheckpointIdColumn(),
                $checkpoint::select('id')->where($checkpointDateColumn, '<=', $until->$checkpointDateColumn)
            );
        } elseif ($until !== null) {
            $q->where($this->getQualifiedCreatedAtColumn(), '<=', Carbon::parse($until));
        }

        if ($since instanceof $checkpoint) {
            // where in this checkpoint or one of the following ones
            $q->whereIn(
                $this->getCheckpointIdColumn(),
                $checkpoint::select('id')->where($checkpointDateColumn, '>', $since->$checkpointDateColumn)
            );
        } elseif ($since !== null) {
            $q->where($this->getQualifiedCreatedAtColumn(), '>', Carbon::parse($since));
        }

        return $q;
    }
}
