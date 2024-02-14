<?php

namespace NextDeveloper\Events\Http\Requests\Listeners;

use NextDeveloper\Commons\Http\Requests\AbstractFormRequest;

class ListenersCreateRequest extends AbstractFormRequest
{

    /**
     * @return array
     */
    public function rules()
    {
        return [
            'event' => 'required|string',
        'callback' => 'required|string',
        ];
    }
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE
}