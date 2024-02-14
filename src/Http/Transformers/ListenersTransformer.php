<?php

namespace NextDeveloper\Events\Http\Transformers;

use Illuminate\Support\Facades\Cache;
use NextDeveloper\Commons\Common\Cache\CacheHelper;
use NextDeveloper\Events\Database\Models\Listeners;
use NextDeveloper\Commons\Http\Transformers\AbstractTransformer;
use NextDeveloper\Events\Http\Transformers\AbstractTransformers\AbstractListenersTransformer;

/**
 * Class ListenersTransformer. This class is being used to manipulate the data we are serving to the customer
 *
 * @package NextDeveloper\Events\Http\Transformers
 */
class ListenersTransformer extends AbstractListenersTransformer
{

    /**
     * @param Listeners $model
     *
     * @return array
     */
    public function transform(Listeners $model)
    {
        $transformed = Cache::get(
            CacheHelper::getKey('Listeners', $model->uuid, 'Transformed')
        );

        if($transformed) {
            return $transformed;
        }

        $transformed = parent::transform($model);

        Cache::set(
            CacheHelper::getKey('Listeners', $model->uuid, 'Transformed'),
            $transformed
        );

        return $transformed;
    }
}
