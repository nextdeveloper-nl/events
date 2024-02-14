<?php

namespace NextDeveloper\Events\Http\Transformers\AbstractTransformers;

use NextDeveloper\Events\Database\Models\Listeners;
use NextDeveloper\Commons\Http\Transformers\AbstractTransformer;

/**
 * Class ListenersTransformer. This class is being used to manipulate the data we are serving to the customer
 *
 * @package NextDeveloper\Events\Http\Transformers
 */
class AbstractListenersTransformer extends AbstractTransformer
{

    /**
     * @param Listeners $model
     *
     * @return array
     */
    public function transform(Listeners $model)
    {
                        $iamAccountId = \NextDeveloper\IAM\Database\Models\Accounts::where('id', $model->iam_account_id)->first();
        
        return $this->buildPayload(
            [
            'id'  =>  $model->uuid,
            'event'  =>  $model->event,
            'callback'  =>  $model->callback,
            'iam_account_id'  =>  $iamAccountId ? $iamAccountId->uuid : null,
            'created_at'  =>  $model->created_at,
            'updated_at'  =>  $model->updated_at,
            'deleted_at'  =>  $model->deleted_at,
            ]
        );
    }

    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE


}
