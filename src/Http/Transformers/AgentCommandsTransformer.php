<?php

namespace NextDeveloper\Events\Http\Transformers;

use Illuminate\Support\Facades\Cache;
use NextDeveloper\Commons\Common\Cache\CacheHelper;
use NextDeveloper\Events\Database\Models\AgentCommands;
use NextDeveloper\Commons\Http\Transformers\AbstractTransformer;
use NextDeveloper\Events\Http\Transformers\AbstractTransformers\AbstractAgentCommandsTransformer;

/**
 * Class AgentCommandsTransformer. This class is being used to manipulate the data we are serving to the customer
 *
 * @package NextDeveloper\Events\Http\Transformers
 */
class AgentCommandsTransformer extends AbstractAgentCommandsTransformer
{

    /**
     * @param AgentCommands $model
     *
     * @return array
     */
    public function transform(AgentCommands $model)
    {
        $transformed = Cache::get(
            CacheHelper::getKey('AgentCommands', $model->uuid, 'Transformed')
        );

        if($transformed) {
            return $transformed;
        }

        $transformed = parent::transform($model);

        Cache::set(
            CacheHelper::getKey('AgentCommands', $model->uuid, 'Transformed'),
            $transformed
        );

        return $transformed;
    }
}
