<?php

namespace NextDeveloper\Events\Tests\Database\Models;

use Tests\TestCase;
use GuzzleHttp\Client;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use NextDeveloper\Events\Database\Filters\EventListenerQueryFilter;
use NextDeveloper\Events\Services\AbstractServices\AbstractEventListenerService;
use Illuminate\Pagination\LengthAwarePaginator;
use League\Fractal\Resource\Collection;

trait EventListenerTestTraits
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

    public function test_http_eventlistener_get()
    {
        $this->setupGuzzle();
        $response = $this->http->request(
            'GET',
            '/events/eventlistener',
            ['http_errors' => false]
        );

        $this->assertContains(
            $response->getStatusCode(), [
            Response::HTTP_OK,
            Response::HTTP_NOT_FOUND
            ]
        );
    }

    public function test_http_eventlistener_post()
    {
        $this->setupGuzzle();
        $response = $this->http->request(
            'POST', '/events/eventlistener', [
            'form_params'   =>  [
                'event'  =>  'a',
                'callback'  =>  'a',
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
    public function test_eventlistener_model_get()
    {
        $result = AbstractEventListenerService::get();

        $this->assertIsObject($result, Collection::class);
    }

    public function test_eventlistener_get_all()
    {
        $result = AbstractEventListenerService::getAll();

        $this->assertIsObject($result, Collection::class);
    }

    public function test_eventlistener_get_paginated()
    {
        $result = AbstractEventListenerService::get(
            null, [
            'paginated' =>  'true'
            ]
        );

        $this->assertIsObject($result, LengthAwarePaginator::class);
    }

    public function test_eventlistener_event_retrieved_without_object()
    {
        try {
            event(new \NextDeveloper\Events\Events\EventListener\EventListenerRetrievedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_eventlistener_event_created_without_object()
    {
        try {
            event(new \NextDeveloper\Events\Events\EventListener\EventListenerCreatedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_eventlistener_event_creating_without_object()
    {
        try {
            event(new \NextDeveloper\Events\Events\EventListener\EventListenerCreatingEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_eventlistener_event_saving_without_object()
    {
        try {
            event(new \NextDeveloper\Events\Events\EventListener\EventListenerSavingEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_eventlistener_event_saved_without_object()
    {
        try {
            event(new \NextDeveloper\Events\Events\EventListener\EventListenerSavedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_eventlistener_event_updating_without_object()
    {
        try {
            event(new \NextDeveloper\Events\Events\EventListener\EventListenerUpdatingEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_eventlistener_event_updated_without_object()
    {
        try {
            event(new \NextDeveloper\Events\Events\EventListener\EventListenerUpdatedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_eventlistener_event_deleting_without_object()
    {
        try {
            event(new \NextDeveloper\Events\Events\EventListener\EventListenerDeletingEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_eventlistener_event_deleted_without_object()
    {
        try {
            event(new \NextDeveloper\Events\Events\EventListener\EventListenerDeletedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_eventlistener_event_restoring_without_object()
    {
        try {
            event(new \NextDeveloper\Events\Events\EventListener\EventListenerRestoringEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_eventlistener_event_restored_without_object()
    {
        try {
            event(new \NextDeveloper\Events\Events\EventListener\EventListenerRestoredEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_eventlistener_event_retrieved_with_object()
    {
        try {
            $model = \NextDeveloper\Events\Database\Models\EventListener::first();

            event(new \NextDeveloper\Events\Events\EventListener\EventListenerRetrievedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_eventlistener_event_created_with_object()
    {
        try {
            $model = \NextDeveloper\Events\Database\Models\EventListener::first();

            event(new \NextDeveloper\Events\Events\EventListener\EventListenerCreatedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_eventlistener_event_creating_with_object()
    {
        try {
            $model = \NextDeveloper\Events\Database\Models\EventListener::first();

            event(new \NextDeveloper\Events\Events\EventListener\EventListenerCreatingEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_eventlistener_event_saving_with_object()
    {
        try {
            $model = \NextDeveloper\Events\Database\Models\EventListener::first();

            event(new \NextDeveloper\Events\Events\EventListener\EventListenerSavingEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_eventlistener_event_saved_with_object()
    {
        try {
            $model = \NextDeveloper\Events\Database\Models\EventListener::first();

            event(new \NextDeveloper\Events\Events\EventListener\EventListenerSavedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_eventlistener_event_updating_with_object()
    {
        try {
            $model = \NextDeveloper\Events\Database\Models\EventListener::first();

            event(new \NextDeveloper\Events\Events\EventListener\EventListenerUpdatingEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_eventlistener_event_updated_with_object()
    {
        try {
            $model = \NextDeveloper\Events\Database\Models\EventListener::first();

            event(new \NextDeveloper\Events\Events\EventListener\EventListenerUpdatedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_eventlistener_event_deleting_with_object()
    {
        try {
            $model = \NextDeveloper\Events\Database\Models\EventListener::first();

            event(new \NextDeveloper\Events\Events\EventListener\EventListenerDeletingEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_eventlistener_event_deleted_with_object()
    {
        try {
            $model = \NextDeveloper\Events\Database\Models\EventListener::first();

            event(new \NextDeveloper\Events\Events\EventListener\EventListenerDeletedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_eventlistener_event_restoring_with_object()
    {
        try {
            $model = \NextDeveloper\Events\Database\Models\EventListener::first();

            event(new \NextDeveloper\Events\Events\EventListener\EventListenerRestoringEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_eventlistener_event_restored_with_object()
    {
        try {
            $model = \NextDeveloper\Events\Database\Models\EventListener::first();

            event(new \NextDeveloper\Events\Events\EventListener\EventListenerRestoredEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_eventlistener_event_event_filter()
    {
        try {
            $request = new Request(
                [
                'event'  =>  'a'
                ]
            );

            $filter = new EventListenerQueryFilter($request);

            $model = \NextDeveloper\Events\Database\Models\EventListener::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_eventlistener_event_callback_filter()
    {
        try {
            $request = new Request(
                [
                'callback'  =>  'a'
                ]
            );

            $filter = new EventListenerQueryFilter($request);

            $model = \NextDeveloper\Events\Database\Models\EventListener::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_eventlistener_event_created_at_filter_start()
    {
        try {
            $request = new Request(
                [
                'created_atStart'  =>  now()
                ]
            );

            $filter = new EventListenerQueryFilter($request);

            $model = \NextDeveloper\Events\Database\Models\EventListener::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_eventlistener_event_updated_at_filter_start()
    {
        try {
            $request = new Request(
                [
                'updated_atStart'  =>  now()
                ]
            );

            $filter = new EventListenerQueryFilter($request);

            $model = \NextDeveloper\Events\Database\Models\EventListener::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_eventlistener_event_deleted_at_filter_start()
    {
        try {
            $request = new Request(
                [
                'deleted_atStart'  =>  now()
                ]
            );

            $filter = new EventListenerQueryFilter($request);

            $model = \NextDeveloper\Events\Database\Models\EventListener::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_eventlistener_event_created_at_filter_end()
    {
        try {
            $request = new Request(
                [
                'created_atEnd'  =>  now()
                ]
            );

            $filter = new EventListenerQueryFilter($request);

            $model = \NextDeveloper\Events\Database\Models\EventListener::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_eventlistener_event_updated_at_filter_end()
    {
        try {
            $request = new Request(
                [
                'updated_atEnd'  =>  now()
                ]
            );

            $filter = new EventListenerQueryFilter($request);

            $model = \NextDeveloper\Events\Database\Models\EventListener::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_eventlistener_event_deleted_at_filter_end()
    {
        try {
            $request = new Request(
                [
                'deleted_atEnd'  =>  now()
                ]
            );

            $filter = new EventListenerQueryFilter($request);

            $model = \NextDeveloper\Events\Database\Models\EventListener::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_eventlistener_event_created_at_filter_start_and_end()
    {
        try {
            $request = new Request(
                [
                'created_atStart'  =>  now(),
                'created_atEnd'  =>  now()
                ]
            );

            $filter = new EventListenerQueryFilter($request);

            $model = \NextDeveloper\Events\Database\Models\EventListener::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_eventlistener_event_updated_at_filter_start_and_end()
    {
        try {
            $request = new Request(
                [
                'updated_atStart'  =>  now(),
                'updated_atEnd'  =>  now()
                ]
            );

            $filter = new EventListenerQueryFilter($request);

            $model = \NextDeveloper\Events\Database\Models\EventListener::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_eventlistener_event_deleted_at_filter_start_and_end()
    {
        try {
            $request = new Request(
                [
                'deleted_atStart'  =>  now(),
                'deleted_atEnd'  =>  now()
                ]
            );

            $filter = new EventListenerQueryFilter($request);

            $model = \NextDeveloper\Events\Database\Models\EventListener::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE
}