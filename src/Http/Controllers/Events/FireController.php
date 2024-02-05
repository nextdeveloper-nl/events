<?php

use NextDeveloper\Commons\Http\Controllers\AbstractController;
use NextDeveloper\Events\Http\Requests\Events\EventFireRequest;
use NextDeveloper\Events\Services\Events;
use NextDeveloper\IAM\Helpers\UserHelper;

class FireController extends AbstractController
{
    public function fire(EventFireRequest $request)
    {
        $me = UserHelper::me();

        if(!$me->hasPermission('fire-event')) {
            return response()->json([
                'error' =>  'PermissionDenied'
            ]);
        }

        Events::fire($request->get('event'), $request->get('data'));
    }
}
