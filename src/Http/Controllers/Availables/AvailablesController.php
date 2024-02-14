<?php

namespace NextDeveloper\Events\Http\Controllers\Availables;

use Illuminate\Http\Request;
use NextDeveloper\Events\Http\Controllers\AbstractController;
use NextDeveloper\Commons\Http\Response\ResponsableFactory;
use NextDeveloper\Events\Http\Requests\Availables\AvailablesUpdateRequest;
use NextDeveloper\Events\Database\Filters\AvailablesQueryFilter;
use NextDeveloper\Events\Database\Models\Availables;
use NextDeveloper\Events\Services\AvailablesService;
use NextDeveloper\Events\Http\Requests\Availables\AvailablesCreateRequest;
use NextDeveloper\Commons\Http\Traits\Tags;use NextDeveloper\Commons\Http\Traits\Addresses;
class AvailablesController extends AbstractController
{
    private $model = Availables::class;

    use Tags;
    use Addresses;
    /**
     * This method returns the list of availables.
     *
     * optional http params:
     * - paginate: If you set paginate parameter, the result will be returned paginated.
     *
     * @param  AvailablesQueryFilter $filter  An object that builds search query
     * @param  Request               $request Laravel request object, this holds all data about request. Automatically populated.
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(AvailablesQueryFilter $filter, Request $request)
    {
        $data = AvailablesService::get($filter, $request->all());

        return ResponsableFactory::makeResponse($this, $data);
    }

    /**
     * This method receives ID for the related model and returns the item to the client.
     *
     * @param  $availablesId
     * @return mixed|null
     * @throws \Laravel\Octane\Exceptions\DdException
     */
    public function show($ref)
    {
        //  Here we are not using Laravel Route Model Binding. Please check routeBinding.md file
        //  in NextDeveloper Platform Project
        $model = AvailablesService::getByRef($ref);

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
        $objects = AvailablesService::relatedObjects($ref, $subObject);

        return ResponsableFactory::makeResponse($this, $objects);
    }

    /**
     * This method created Availables object on database.
     *
     * @param  AvailablesCreateRequest $request
     * @return mixed|null
     * @throws \NextDeveloper\Commons\Exceptions\CannotCreateModelException
     */
    public function store(AvailablesCreateRequest $request)
    {
        $model = AvailablesService::create($request->validated());

        return ResponsableFactory::makeResponse($this, $model);
    }

    /**
     * This method updates Availables object on database.
     *
     * @param  $availablesId
     * @param  CountryCreateRequest $request
     * @return mixed|null
     * @throws \NextDeveloper\Commons\Exceptions\CannotCreateModelException
     */
    public function update($availablesId, AvailablesUpdateRequest $request)
    {
        $model = AvailablesService::update($availablesId, $request->validated());

        return ResponsableFactory::makeResponse($this, $model);
    }

    /**
     * This method updates Availables object on database.
     *
     * @param  $availablesId
     * @param  CountryCreateRequest $request
     * @return mixed|null
     * @throws \NextDeveloper\Commons\Exceptions\CannotCreateModelException
     */
    public function destroy($availablesId)
    {
        $model = AvailablesService::delete($availablesId);

        return $this->noContent();
    }

    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE

}
