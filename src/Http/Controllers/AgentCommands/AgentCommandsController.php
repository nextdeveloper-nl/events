<?php

namespace NextDeveloper\Events\Http\Controllers\AgentCommands;

use Illuminate\Http\Request;
use NextDeveloper\Events\Http\Controllers\AbstractController;
use NextDeveloper\Commons\Http\Response\ResponsableFactory;
use NextDeveloper\Events\Http\Requests\AgentCommands\AgentCommandsUpdateRequest;
use NextDeveloper\Events\Database\Filters\AgentCommandsQueryFilter;
use NextDeveloper\Events\Database\Models\AgentCommands;
use NextDeveloper\Events\Services\AgentCommandsService;
use NextDeveloper\Events\Http\Requests\AgentCommands\AgentCommandsCreateRequest;
use NextDeveloper\Commons\Http\Traits\Tags as TagsTrait;use NextDeveloper\Commons\Http\Traits\Addresses as AddressesTrait;
class AgentCommandsController extends AbstractController
{
    private $model = AgentCommands::class;

    use TagsTrait;
    use AddressesTrait;
    /**
     * This method returns the list of agentcommands.
     *
     * optional http params:
     * - paginate: If you set paginate parameter, the result will be returned paginated.
     *
     * @param  AgentCommandsQueryFilter $filter  An object that builds search query
     * @param  Request                  $request Laravel request object, this holds all data about request. Automatically populated.
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(AgentCommandsQueryFilter $filter, Request $request)
    {
        $data = AgentCommandsService::get($filter, $request->all());

        return ResponsableFactory::makeResponse($this, $data);
    }

    /**
     * This function returns the list of actions that can be performed on this object.
     *
     * @return void
     */
    public function getActions()
    {
        $data = AgentCommandsService::getActions();

        return ResponsableFactory::makeResponse($this, $data);
    }

    /**
     * Makes the related action to the object
     *
     * @param  $objectId
     * @param  $action
     * @return array
     */
    public function doAction($objectId, $action)
    {
        $actionId = AgentCommandsService::doAction($objectId, $action, request()->all());

        return $this->withArray(
            [
            'action_id' =>  $actionId
            ]
        );
    }

    /**
     * This method receives ID for the related model and returns the item to the client.
     *
     * @param  $agentCommandsId
     * @return mixed|null
     * @throws \Laravel\Octane\Exceptions\DdException
     */
    public function show($ref)
    {
        //  Here we are not using Laravel Route Model Binding. Please check routeBinding.md file
        //  in NextDeveloper Platform Project
        $model = AgentCommandsService::getByRef($ref);

        return ResponsableFactory::makeResponse($this, $model);
    }

    /**
     * This method returns the list of sub objects the related object. Sub object means an object which is preowned by
     * this object.
     *
     * It can be tags, addresses, states etc.
     *
     * @param  $ref
     * @param  $subObject
     * @return void
     */
    public function relatedObjects($ref, $subObject)
    {
        $objects = AgentCommandsService::relatedObjects($ref, $subObject);

        return ResponsableFactory::makeResponse($this, $objects);
    }

    /**
     * This method created AgentCommands object on database.
     *
     * @param  AgentCommandsCreateRequest $request
     * @return mixed|null
     * @throws \NextDeveloper\Commons\Exceptions\CannotCreateModelException
     */
    public function store(AgentCommandsCreateRequest $request)
    {
        if($request->has('validateOnly') && $request->get('validateOnly') == true) {
            return [
                'validation'    =>  'success'
            ];
        }

        $model = AgentCommandsService::create($request->validated());

        return ResponsableFactory::makeResponse($this, $model);
    }

    /**
     * This method updates AgentCommands object on database.
     *
     * @param  $agentCommandsId
     * @param  AgentCommandsUpdateRequest $request
     * @return mixed|null
     * @throws \NextDeveloper\Commons\Exceptions\CannotCreateModelException
     */
    public function update($agentCommandsId, AgentCommandsUpdateRequest $request)
    {
        if($request->has('validateOnly') && $request->get('validateOnly') == true) {
            return [
                'validation'    =>  'success'
            ];
        }

        $model = AgentCommandsService::update($agentCommandsId, $request->validated());

        return ResponsableFactory::makeResponse($this, $model);
    }

    /**
     * This method updates AgentCommands object on database.
     *
     * @param  $agentCommandsId
     * @return mixed|null
     * @throws \NextDeveloper\Commons\Exceptions\CannotCreateModelException
     */
    public function destroy($agentCommandsId)
    {
        $model = AgentCommandsService::delete($agentCommandsId);

        return $this->noContent();
    }

    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE

}
