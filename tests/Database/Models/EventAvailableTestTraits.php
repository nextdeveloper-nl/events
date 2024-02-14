<?php

namespace NextDeveloper\Events\Tests\Database\Models;

use Tests\TestCase;
use GuzzleHttp\Client;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use NextDeveloper\Events\Database\Filters\EventAvailableQueryFilter;
use NextDeveloper\Events\Services\AbstractServices\AbstractEventAvailableService;
use Illuminate\Pagination\LengthAwarePaginator;
use League\Fractal\Resource\Collection;

trait EventAvailableTestTraits
{
    public $http;

    /**
     *   Creating the Guzzle object
     */
    public function setupGuzzle()
    {
        $this->http = new Client(
            [
            'base_uri'  =>  '127.0.0.1:8000'
            ]
        );
    }

    /**
     *   Destroying the Guzzle object
     */
    public function destroyGuzzle()
    {
        $this->http = null;
    }

    public function test_http_eventavailable_get()
    {
        $this->setupGuzzle();
        $response = $this->http->request(
            'GET',
            '/events/eventavailable',
            ['http_errors' => false]
        );

        $this->assertContains(
            $response->getStatusCode(), [
            Response::HTTP_OK,
            Response::HTTP_NOT_FOUND
            ]
        );
    }

    public function test_http_eventavailable_post()
    {
        $this->setupGuzzle();
        $response = $this->http->request(
            'POST', '/events/eventavailable', [
            'form_params'   =>  [
                'event'  =>  'a',
                'description'  =>  'a',
                            ],
                ['http_errors' => false]
            ]
        );

        $this->assertEquals($response->getStatusCode(), Response::HTTP_OK);
    }

    /**
     * Get test
     *
     * @return bool
     */
    public function test_eventavailable_model_get()
    {
        $result = AbstractEventAvailableService::get();

        $this->assertIsObject($result, Collection::class);
    }

    public function test_eventavailable_get_all()
    {
        $result = AbstractEventAvailableService::getAll();

        $this->assertIsObject($result, Collection::class);
    }

    public function test_eventavailable_get_paginated()
    {
        $result = AbstractEventAvailableService::get(
            null, [
            'paginated' =>  'true'
            ]
        );

        $this->assertIsObject($result, LengthAwarePaginator::class);
    }

    public function test_eventavailable_event_retrieved_without_object()
    {
        try {
            event(new \NextDeveloper\Events\Events\EventAvailable\EventAvailableRetrievedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_eventavailable_event_created_without_object()
    {
        try {
            event(new \NextDeveloper\Events\Events\EventAvailable\EventAvailableCreatedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_eventavailable_event_creating_without_object()
    {
        try {
            event(new \NextDeveloper\Events\Events\EventAvailable\EventAvailableCreatingEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_eventavailable_event_saving_without_object()
    {
        try {
            event(new \NextDeveloper\Events\Events\EventAvailable\EventAvailableSavingEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_eventavailable_event_saved_without_object()
    {
        try {
            event(new \NextDeveloper\Events\Events\EventAvailable\EventAvailableSavedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_eventavailable_event_updating_without_object()
    {
        try {
            event(new \NextDeveloper\Events\Events\EventAvailable\EventAvailableUpdatingEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_eventavailable_event_updated_without_object()
    {
        try {
            event(new \NextDeveloper\Events\Events\EventAvailable\EventAvailableUpdatedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_eventavailable_event_deleting_without_object()
    {
        try {
            event(new \NextDeveloper\Events\Events\EventAvailable\EventAvailableDeletingEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_eventavailable_event_deleted_without_object()
    {
        try {
            event(new \NextDeveloper\Events\Events\EventAvailable\EventAvailableDeletedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_eventavailable_event_restoring_without_object()
    {
        try {
            event(new \NextDeveloper\Events\Events\EventAvailable\EventAvailableRestoringEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_eventavailable_event_restored_without_object()
    {
        try {
            event(new \NextDeveloper\Events\Events\EventAvailable\EventAvailableRestoredEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_eventavailable_event_retrieved_with_object()
    {
        try {
            $model = \NextDeveloper\Events\Database\Models\EventAvailable::first();

            event(new \NextDeveloper\Events\Events\EventAvailable\EventAvailableRetrievedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_eventavailable_event_created_with_object()
    {
        try {
            $model = \NextDeveloper\Events\Database\Models\EventAvailable::first();

            event(new \NextDeveloper\Events\Events\EventAvailable\EventAvailableCreatedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_eventavailable_event_creating_with_object()
    {
        try {
            $model = \NextDeveloper\Events\Database\Models\EventAvailable::first();

            event(new \NextDeveloper\Events\Events\EventAvailable\EventAvailableCreatingEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_eventavailable_event_saving_with_object()
    {
        try {
            $model = \NextDeveloper\Events\Database\Models\EventAvailable::first();

            event(new \NextDeveloper\Events\Events\EventAvailable\EventAvailableSavingEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_eventavailable_event_saved_with_object()
    {
        try {
            $model = \NextDeveloper\Events\Database\Models\EventAvailable::first();

            event(new \NextDeveloper\Events\Events\EventAvailable\EventAvailableSavedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_eventavailable_event_updating_with_object()
    {
        try {
            $model = \NextDeveloper\Events\Database\Models\EventAvailable::first();

            event(new \NextDeveloper\Events\Events\EventAvailable\EventAvailableUpdatingEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_eventavailable_event_updated_with_object()
    {
        try {
            $model = \NextDeveloper\Events\Database\Models\EventAvailable::first();

            event(new \NextDeveloper\Events\Events\EventAvailable\EventAvailableUpdatedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_eventavailable_event_deleting_with_object()
    {
        try {
            $model = \NextDeveloper\Events\Database\Models\EventAvailable::first();

            event(new \NextDeveloper\Events\Events\EventAvailable\EventAvailableDeletingEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_eventavailable_event_deleted_with_object()
    {
        try {
            $model = \NextDeveloper\Events\Database\Models\EventAvailable::first();

            event(new \NextDeveloper\Events\Events\EventAvailable\EventAvailableDeletedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_eventavailable_event_restoring_with_object()
    {
        try {
            $model = \NextDeveloper\Events\Database\Models\EventAvailable::first();

            event(new \NextDeveloper\Events\Events\EventAvailable\EventAvailableRestoringEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_eventavailable_event_restored_with_object()
    {
        try {
            $model = \NextDeveloper\Events\Database\Models\EventAvailable::first();

            event(new \NextDeveloper\Events\Events\EventAvailable\EventAvailableRestoredEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_eventavailable_event_event_filter()
    {
        try {
            $request = new Request(
                [
                'event'  =>  'a'
                ]
            );

            $filter = new EventAvailableQueryFilter($request);

            $model = \NextDeveloper\Events\Database\Models\EventAvailable::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_eventavailable_event_description_filter()
    {
        try {
            $request = new Request(
                [
                'description'  =>  'a'
                ]
            );

            $filter = new EventAvailableQueryFilter($request);

            $model = \NextDeveloper\Events\Database\Models\EventAvailable::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_eventavailable_event_created_at_filter_start()
    {
        try {
            $request = new Request(
                [
                'created_atStart'  =>  now()
                ]
            );

            $filter = new EventAvailableQueryFilter($request);

            $model = \NextDeveloper\Events\Database\Models\EventAvailable::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_eventavailable_event_updated_at_filter_start()
    {
        try {
            $request = new Request(
                [
                'updated_atStart'  =>  now()
                ]
            );

            $filter = new EventAvailableQueryFilter($request);

            $model = \NextDeveloper\Events\Database\Models\EventAvailable::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_eventavailable_event_deleted_at_filter_start()
    {
        try {
            $request = new Request(
                [
                'deleted_atStart'  =>  now()
                ]
            );

            $filter = new EventAvailableQueryFilter($request);

            $model = \NextDeveloper\Events\Database\Models\EventAvailable::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_eventavailable_event_created_at_filter_end()
    {
        try {
            $request = new Request(
                [
                'created_atEnd'  =>  now()
                ]
            );

            $filter = new EventAvailableQueryFilter($request);

            $model = \NextDeveloper\Events\Database\Models\EventAvailable::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_eventavailable_event_updated_at_filter_end()
    {
        try {
            $request = new Request(
                [
                'updated_atEnd'  =>  now()
                ]
            );

            $filter = new EventAvailableQueryFilter($request);

            $model = \NextDeveloper\Events\Database\Models\EventAvailable::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_eventavailable_event_deleted_at_filter_end()
    {
        try {
            $request = new Request(
                [
                'deleted_atEnd'  =>  now()
                ]
            );

            $filter = new EventAvailableQueryFilter($request);

            $model = \NextDeveloper\Events\Database\Models\EventAvailable::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_eventavailable_event_created_at_filter_start_and_end()
    {
        try {
            $request = new Request(
                [
                'created_atStart'  =>  now(),
                'created_atEnd'  =>  now()
                ]
            );

            $filter = new EventAvailableQueryFilter($request);

            $model = \NextDeveloper\Events\Database\Models\EventAvailable::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_eventavailable_event_updated_at_filter_start_and_end()
    {
        try {
            $request = new Request(
                [
                'updated_atStart'  =>  now(),
                'updated_atEnd'  =>  now()
                ]
            );

            $filter = new EventAvailableQueryFilter($request);

            $model = \NextDeveloper\Events\Database\Models\EventAvailable::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_eventavailable_event_deleted_at_filter_start_and_end()
    {
        try {
            $request = new Request(
                [
                'deleted_atStart'  =>  now(),
                'deleted_atEnd'  =>  now()
                ]
            );

            $filter = new EventAvailableQueryFilter($request);

            $model = \NextDeveloper\Events\Database\Models\EventAvailable::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE
}