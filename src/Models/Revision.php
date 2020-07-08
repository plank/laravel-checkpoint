<?php
namespace Plank\Checkpoint\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphPivot;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
    protected $guarded = ['id', 'previous_revision_id', 'metadata'];

    /**
     * Retrieve the revisioned model associated with this entry
     */
    public function revisionable()
    {
        return $this->morphTo('revisionable');
    }

    /**
     * Retrieve the original model in this sequence
     */
    public function original()
    {
        return $this->morphTo('revisionable', 'revisionable_type', 'original_revisionable_id');
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
     * Return all the revisions that share the same item
     *
     * @return
     */
    public function allRevisions(): HasMany
    {
        return $this
            ->hasMany(self::class, 'revisionable_type', 'revisionable_type')
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
}
