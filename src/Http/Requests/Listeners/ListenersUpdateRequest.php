<?php

namespace NextDeveloper\Events\Http\Requests\Listeners;

use NextDeveloper\Commons\Http\Requests\AbstractFormRequest;

class ListenersUpdateRequest extends AbstractFormRequest
{

    /**
     * @return array
     */
    public function rules()
    {
        return [
            'event' => 'nullable|string',
        'callback' => 'nullable|string',
        'name' => 'nullable|string',
        'is_active' => 'boolean',
        'conditions' => 'nullable',
        'time_window' => 'nullable',
        'priority' => 'nullable|integer',
        'communication_channel_ids' => 'nullable',
        'recipient_iam_account_ids' => 'nullable',
        ];
    }
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE
}