<?php

namespace NextDeveloper\Events\Http\Requests\Availables;

use NextDeveloper\Commons\Http\Requests\AbstractFormRequest;

class AvailablesUpdateRequest extends AbstractFormRequest
{

    /**
     * @return array
     */
    public function rules()
    {
        return [
            'event' => 'nullable|string',
        'description' => 'nullable|string',
        ];
    }
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE
}