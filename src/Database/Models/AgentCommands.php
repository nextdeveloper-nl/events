<?php

namespace NextDeveloper\Events\Database\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use NextDeveloper\Commons\Database\Traits\HasStates;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Model;
use NextDeveloper\Commons\Database\Traits\Filterable;
use NextDeveloper\Events\Database\Observers\AgentCommandsObserver;
use NextDeveloper\Commons\Database\Traits\UuidId;
use NextDeveloper\Commons\Database\Traits\HasObject;
use NextDeveloper\Commons\Common\Cache\Traits\CleanCache;
use NextDeveloper\Commons\Database\Traits\Taggable;
use NextDeveloper\Commons\Database\Traits\RunAsAdministrator;

/**
 * AgentCommands model.
 *
 * @package  NextDeveloper\Events\Database\Models
 * @property integer $id
 * @property string $uuid
 * @property string $agent_type
 * @property string $agent_uuid
 * @property string $operation
 * @property $params
 * @property string $status
 * @property $result
 * @property string $error
 * @property integer $iam_account_id
 * @property integer $iam_user_id
 * @property \Carbon\Carbon $sent_at
 * @property \Carbon\Carbon $completed_at
 * @property \Carbon\Carbon $timeout_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon $deleted_at
 */
class AgentCommands extends Model
{
    use Filterable, UuidId, CleanCache, Taggable, HasStates, RunAsAdministrator, HasObject;
    use SoftDeletes;

    public $timestamps = true;

    protected $table = 'event_agent_commands';


    /**
     @var array
     */
    protected $guarded = [];

    protected $fillable = [
            'agent_type',
            'agent_uuid',
            'operation',
            'params',
            'status',
            'result',
            'error',
            'iam_account_id',
            'iam_user_id',
            'sent_at',
            'completed_at',
            'timeout_at',
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
    'agent_type' => 'string',
    'operation' => 'string',
    'params' => 'array',
    'status' => 'string',
    'result' => 'array',
    'error' => 'string',
    'sent_at' => 'datetime',
    'completed_at' => 'datetime',
    'timeout_at' => 'datetime',
    'created_at' => 'datetime',
    'updated_at' => 'datetime',
    'deleted_at' => 'datetime',
    ];

    /**
     We are casting data fields.
     *
     @var array
     */
    protected $dates = [
    'sent_at',
    'completed_at',
    'timeout_at',
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
        parent::observe(AgentCommandsObserver::class);

        self::registerScopes();
    }

    public static function registerScopes()
    {
        $globalScopes = config('events.scopes.global');
        $modelScopes = config('events.scopes.event_agent_commands');

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

    public function accounts() : \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\NextDeveloper\IAM\Database\Models\Accounts::class);
    }
    
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE
}
