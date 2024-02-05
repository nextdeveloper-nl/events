<?php

namespace NextDeveloper\Events\Http\Requests\Events;

use NextDeveloper\Commons\Http\Requests\AbstractFormRequest;

class EventFireRequest extends AbstractFormRequest
{

    /**
     * @return array
     */
    public function rules()
    {
        return [
            'event' =>  'required|string',
            'data'  =>  'required|string'
        ];
    }
}
