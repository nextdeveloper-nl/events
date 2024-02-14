<?php

namespace NextDeveloper\Events\Http\Controllers\Listeners;

use Illuminate\Http\Request;
use NextDeveloper\Events\Http\Controllers\AbstractController;
use NextDeveloper\Commons\Http\Response\ResponsableFactory;
use NextDeveloper\Events\Http\Requests\Listeners\ListenersUpdateRequest;
use NextDeveloper\Events\Database\Filters\ListenersQueryFilter;
use NextDeveloper\Events\Database\Models\Listeners;
use NextDeveloper\Events\Services\ListenersService;
use NextDeveloper\Events\Http\Requests\Listeners\ListenersCreateRequest;
use NextDeveloper\Commons\Http\Traits\Tags;use NextDeveloper\Commons\Http\Traits\Addresses;
class ListenersController extends AbstractController
{
    private $model = Listeners::class;

    use Tags;
    use Addresses;
    /**
     * This method returns the list of listeners.
     *
     * optional http params:
     * - paginate: If you set paginate parameter, the result will be returned paginated.
     *
     * @param  ListenersQueryFilter $filter  An object that builds search query
     * @param  Request              $request Laravel request object, this holds all data about request. Automatically populated.
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(ListenersQueryFilter $filter, Request $request)
    {
        $data = ListenersService::get($filter, $request->all());

        return ResponsableFactory::makeResponse($this, $data);
    }

    /**
     * This method receives ID for the related model and returns the item to the client.
     *
     * @param  $listenersId
     * @return mixed|null
     * @throws \Laravel\Octane\Exceptions\DdException
     */
    public function show($ref)
    {
        //  Here we are not using Laravel Route Model Binding. Please check routeBinding.md file
        //  in NextDeveloper Platform Project
        $model = ListenersService::getByRef($ref);

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
        $objects = ListenersService::relatedObjects($ref, $subObject);

        return ResponsableFactory::makeResponse($this, $objects);
    }

    /**
     * This method created Listeners object on database.
     *
     * @param  ListenersCreateRequest $request
     * @return mixed|null
     * @throws \NextDeveloper\Commons\Exceptions\CannotCreateModelException
     */
    public function store(ListenersCreateRequest $request)
    {
        $model = ListenersService::create($request->validated());

        return ResponsableFactory::makeResponse($this, $model);
    }

    /**
     * This method updates Listeners object on database.
     *
     * @param  $listenersId
     * @param  CountryCreateRequest $request
     * @return mixed|null
     * @throws \NextDeveloper\Commons\Exceptions\CannotCreateModelException
     */
    public function update($listenersId, ListenersUpdateRequest $request)
    {
        $model = ListenersService::update($listenersId, $request->validated());

        return ResponsableFactory::makeResponse($this, $model);
    }

    /**
     * This method updates Listeners object on database.
     *
     * @param  $listenersId
     * @param  CountryCreateRequest $request
     * @return mixed|null
     * @throws \NextDeveloper\Commons\Exceptions\CannotCreateModelException
     */
    public function destroy($listenersId)
    {
        $model = ListenersService::delete($listenersId);

        return $this->noContent();
    }

    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE

}
