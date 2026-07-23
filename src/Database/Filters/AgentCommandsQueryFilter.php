<?php

namespace NextDeveloper\Events\Database\Filters;

use Illuminate\Database\Eloquent\Builder;
use NextDeveloper\Commons\Database\Filters\AbstractQueryFilter;
        

/**
 * This class automatically puts where clause on database so that use can filter
 * data returned from the query.
 */
class AgentCommandsQueryFilter extends AbstractQueryFilter
{

    /**
     * @var Builder
     */
    protected $builder;

    public function agentType($value)
    {
        return $this->builder->where('agent_type', 'ilike', '%' . $value . '%');
    }

        //  This is an alias function of agentType
    public function agent_type($value)
    {
        return $this->agentType($value);
    }

    public function operation($value)
    {
        return $this->builder->where('operation', 'ilike', '%' . $value . '%');
    }


    public function status($value)
    {
        return $this->builder->where('status', 'ilike', '%' . $value . '%');
    }


    public function error($value)
    {
        return $this->builder->where('error', 'ilike', '%' . $value . '%');
    }


    public function sentAtStart($date)
    {
        return $this->builder->where('sent_at', '>=', $date);
    }

    public function sentAtEnd($date)
    {
        return $this->builder->where('sent_at', '<=', $date);
    }

    //  This is an alias function of sentAt
    public function sent_at_start($value)
    {
        return $this->sentAtStart($value);
    }

    //  This is an alias function of sentAt
    public function sent_at_end($value)
    {
        return $this->sentAtEnd($value);
    }

    public function completedAtStart($date)
    {
        return $this->builder->where('completed_at', '>=', $date);
    }

    public function completedAtEnd($date)
    {
        return $this->builder->where('completed_at', '<=', $date);
    }

    //  This is an alias function of completedAt
    public function completed_at_start($value)
    {
        return $this->completedAtStart($value);
    }

    //  This is an alias function of completedAt
    public function completed_at_end($value)
    {
        return $this->completedAtEnd($value);
    }

    public function timeoutAtStart($date)
    {
        return $this->builder->where('timeout_at', '>=', $date);
    }

    public function timeoutAtEnd($date)
    {
        return $this->builder->where('timeout_at', '<=', $date);
    }

    //  This is an alias function of timeoutAt
    public function timeout_at_start($value)
    {
        return $this->timeoutAtStart($value);
    }

    //  This is an alias function of timeoutAt
    public function timeout_at_end($value)
    {
        return $this->timeoutAtEnd($value);
    }

    public function createdAtStart($date)
    {
        return $this->builder->where('created_at', '>=', $date);
    }

    public function createdAtEnd($date)
    {
        return $this->builder->where('created_at', '<=', $date);
    }

    //  This is an alias function of createdAt
    public function created_at_start($value)
    {
        return $this->createdAtStart($value);
    }

    //  This is an alias function of createdAt
    public function created_at_end($value)
    {
        return $this->createdAtEnd($value);
    }

    public function updatedAtStart($date)
    {
        return $this->builder->where('updated_at', '>=', $date);
    }

    public function updatedAtEnd($date)
    {
        return $this->builder->where('updated_at', '<=', $date);
    }

    //  This is an alias function of updatedAt
    public function updated_at_start($value)
    {
        return $this->updatedAtStart($value);
    }

    //  This is an alias function of updatedAt
    public function updated_at_end($value)
    {
        return $this->updatedAtEnd($value);
    }

    public function deletedAtStart($date)
    {
        return $this->builder->where('deleted_at', '>=', $date);
    }

    public function deletedAtEnd($date)
    {
        return $this->builder->where('deleted_at', '<=', $date);
    }

    //  This is an alias function of deletedAt
    public function deleted_at_start($value)
    {
        return $this->deletedAtStart($value);
    }

    //  This is an alias function of deletedAt
    public function deleted_at_end($value)
    {
        return $this->deletedAtEnd($value);
    }

    public function iamAccountId($value)
    {
            $iamAccount = \NextDeveloper\IAM\Database\Models\Accounts::where('uuid', $value)->first();

        if($iamAccount) {
            return $this->builder->where('iam_account_id', '=', $iamAccount->id);
        }
    }


    public function iamUserId($value)
    {
            $iamUser = \NextDeveloper\IAM\Database\Models\Users::where('uuid', $value)->first();

        if($iamUser) {
            return $this->builder->where('iam_user_id', '=', $iamUser->id);
        }
    }


    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE
}
