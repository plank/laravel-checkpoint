<?php
namespace Plank\Checkpoint\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphPivot;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

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
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [
        'id',
        'metadata'
    ];

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
     * Return the associated checkpoint/release to this revision
     *
     * @return BelongsTo
     */
    public function checkpoint(): BelongsTo
    {
        $model = config('checkpoint.checkpoint_model', Checkpoint::class);
        return $this->belongsTo($model, 'checkpoint_id', (new $model)->getKeyName());
    }

    /**
     * Retrieve the original model in this sequence
     *
     * @return MorphTo
     */
    public function initialRevision(): MorphTo
    {
        return $this->morphTo('revisionable', 'revisionable_type', 'original_revisionable_id');
    }

    /**
     * Return the revision made right before this one
     *
     * @return BelongsTo
     */
    public function previous(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_revision_id', $this->primaryKey);
    }

    /**
     * Return the revision that follows this one
     *
     * @return BelongsTo
     */
    public function next(): HasOne
    {
        return $this->hasOne(self::class, 'previous_revision_id', $this->primaryKey);
    }

    /**
     * Returns true if this is the most current revision for an item
     *
     * @return bool
     */
    public function isLatestRevision(): bool
    {
        return $this->next()->get()->isEmpty();
    }

    /**
     * Return all the revisions that share the same item
     *
     * @return
     */
    public function allRevisions(): HasMany
    {
        return $this->hasMany(self::class, 'revisionable_type', 'revisionable_type')
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
            ->groupBy(['original_revisionable_id', 'revisionable_type']);

        $checkpoint = config('checkpoint.checkpoint_model', Checkpoint::class);
        $checkpointDateColumn = (new $checkpoint)->getCheckpointDateColumn();

        if ($until instanceof $checkpoint) {
            // where in this checkpoint or one of the previous ones
            $q->whereIn(
                'checkpoint_id',
                $checkpoint::select('id')->where($checkpointDateColumn, '<=', $until->$checkpointDateColumn)
            );
        } elseif ($until !== null) {
            $q->where($this->getQualifiedCreatedAtColumn(), '<=', Carbon::parse($until));
        }

        if ($since instanceof $checkpoint) {
            // where in this checkpoint or one of the following ones
            $q->whereIn(
                'checkpoint_id',
                $checkpoint::select('id')->where($checkpointDateColumn, '>', $since->$checkpointDateColumn)
            );
        } elseif ($since !== null) {
            $q->where($this->getQualifiedCreatedAtColumn(), '>', Carbon::parse($since));
        }

        return $q;
    }
}
