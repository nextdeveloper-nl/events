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
        ];
    }
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE
}