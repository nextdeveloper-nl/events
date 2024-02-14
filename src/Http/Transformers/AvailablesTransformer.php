<?php

namespace NextDeveloper\Events\Http\Transformers;

use Illuminate\Support\Facades\Cache;
use NextDeveloper\Commons\Common\Cache\CacheHelper;
use NextDeveloper\Events\Database\Models\Availables;
use NextDeveloper\Commons\Http\Transformers\AbstractTransformer;
use NextDeveloper\Events\Http\Transformers\AbstractTransformers\AbstractAvailablesTransformer;

/**
 * Class AvailablesTransformer. This class is being used to manipulate the data we are serving to the customer
 *
 * @package NextDeveloper\Events\Http\Transformers
 */
class AvailablesTransformer extends AbstractAvailablesTransformer
{

    /**
     * @param Availables $model
     *
     * @return array
     */
    public function transform(Availables $model)
    {
        $transformed = Cache::get(
            CacheHelper::getKey('Availables', $model->uuid, 'Transformed')
        );

        if($transformed) {
            return $transformed;
        }

        $transformed = parent::transform($model);

        Cache::set(
            CacheHelper::getKey('Availables', $model->uuid, 'Transformed'),
            $transformed
        );

        return $transformed;
    }
}
