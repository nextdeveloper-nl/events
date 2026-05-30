<?php

namespace NextDeveloper\Events\Http\Requests\AgentCommands;

use NextDeveloper\Commons\Http\Requests\AbstractFormRequest;

class AgentCommandsCreateRequest extends AbstractFormRequest
{

    /**
     * @return array
     */
    public function rules()
    {
        return [
            'agent_type' => 'required|string',
        'operation' => 'required|string',
        'params' => 'nullable',
        'status' => 'string',
        'result' => 'nullable',
        'error' => 'nullable|string',
        'sent_at' => 'nullable|date',
        'completed_at' => 'nullable|date',
        'timeout_at' => 'nullable|date',
        ];
    }
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE
}