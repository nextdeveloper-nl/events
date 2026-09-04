<?php

namespace NextDeveloper\Events\Database\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use NextDeveloper\Commons\Common\Cache\Traits\CleanCache;
use NextDeveloper\Commons\Database\Traits\Filterable;
use NextDeveloper\Commons\Database\Traits\HasStates;
use NextDeveloper\Commons\Database\Traits\Taggable;
use NextDeveloper\Commons\Database\Traits\UuidId;
use NextDeveloper\Events\Database\Observers\ListenersObserver;
use Illuminate\Notifications\Notifiable;
use NextDeveloper\Commons\Database\Traits\HasObject;
use NextDeveloper\Commons\Database\Traits\RunAsAdministrator;

/**
 * Listeners model.
 *
 * @package  NextDeveloper\Events\Database\Models
 * @property integer $id
 * @property string $uuid
 * @property string $event
 * @property string $callback
 * @property integer $iam_account_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon $deleted_at
 * @property string $name
 * @property boolean $is_active
 * @property  $conditions
 * @property  $time_window
 * @property integer $priority
 * @property array $communication_channel_ids
 * @property array $recipient_iam_account_ids
 */
class Listeners extends Model
{
    use Filterable, UuidId, CleanCache, Taggable, HasStates, RunAsAdministrator, HasObject;
    use SoftDeletes;

    public $timestamps = true;

    protected $table = 'event_listeners';


    /**
     @var array
     */
    protected $guarded = [];

    protected $fillable = [
            'event',
            'callback',
            'iam_account_id',
            'name',
            'is_active',
            'conditions',
            'time_window',
            'priority',
            'communication_channel_ids',
            'recipient_iam_account_ids',
    ];

    /**
      Here we have the fulltext fields. We can use these for fulltext search if enabled.
     */
    protected $fullTextFields = [

    ];

    /**
     @var array
     */
    protected $appends = [

    ];

    /**
     We are casting fields to objects so that we can work on them better
     *
     @var array
     */
    protected $casts = [
    'id' => 'integer',
    'event' => 'string',
    'callback' => 'string',
    'created_at' => 'datetime',
    'updated_at' => 'datetime',
    'deleted_at' => 'datetime',
    'name' => 'string',
    'is_active' => 'boolean',
    'conditions' => 'array',
    'time_window' => 'array',
    'priority' => 'integer',
    'communication_channel_ids' => \NextDeveloper\Commons\Database\Casts\IntegerArray::class,
    'recipient_iam_account_ids' => \NextDeveloper\Commons\Database\Casts\IntegerArray::class,
    ];

    /**
     We are casting data fields.
     *
     @var array
     */
    protected $dates = [
    'created_at',
    'updated_at',
    'deleted_at',
    ];

    /**
     @var array
     */
    protected $with = [

    ];

    /**
     @var int
     */
    protected $perPage = 20;

    /**
     @return void
     */
    public static function boot()
    {
        parent::boot();

        //  We create and add Observer even if we wont use it.
        parent::observe(ListenersObserver::class);

        self::registerScopes();
    }

    public static function registerScopes()
    {
        $globalScopes = config('events.scopes.global');
        $modelScopes = config('events.scopes.event_listeners');

        if(!$modelScopes) { $modelScopes = [];
        }
        if (!$globalScopes) { $globalScopes = [];
        }

        $scopes = array_merge(
            $globalScopes,
            $modelScopes
        );

        if($scopes) {
            foreach ($scopes as $scope) {
                static::addGlobalScope(app($scope));
            }
        }
    }

    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE







}
