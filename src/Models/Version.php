<?php
namespace Plank\Versionable\Models;

use Illuminate\Database\Eloquent\Model;

class Version extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'versions';

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
    protected $guarded = ['*'];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array
     */
    protected $hidden = [];

    /*
     * @return \Illuminate\Database\Eloquent\Relations\Morph
     *
    public function versionables() {

    }
    */


}
