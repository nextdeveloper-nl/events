<?php

namespace NextDeveloper\Events\Http\Transformers\AbstractTransformers;

use NextDeveloper\Events\Database\Models\Availables;
use NextDeveloper\Commons\Http\Transformers\AbstractTransformer;

/**
 * Class AvailablesTransformer. This class is being used to manipulate the data we are serving to the customer
 *
 * @package NextDeveloper\Events\Http\Transformers
 */
class AbstractAvailablesTransformer extends AbstractTransformer
{

    /**
     * @param Availables $model
     *
     * @return array
     */
    public function transform(Availables $model)
    {
            
        return $this->buildPayload(
            [
            'id'  =>  $model->uuid,
            'event'  =>  $model->event,
            'description'  =>  $model->description,
            'created_at'  =>  $model->created_at,
            'updated_at'  =>  $model->updated_at,
            'deleted_at'  =>  $model->deleted_at,
            ]
        );
    }

    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE





}
