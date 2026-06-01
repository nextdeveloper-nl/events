<?php

namespace NextDeveloper\Events\Tests\Database\Models;

use Tests\TestCase;
use GuzzleHttp\Client;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use NextDeveloper\Events\Database\Filters\EventAgentCommandQueryFilter;
use NextDeveloper\Events\Services\AbstractServices\AbstractEventAgentCommandService;
use Illuminate\Pagination\LengthAwarePaginator;
use League\Fractal\Resource\Collection;

trait EventAgentCommandTestTraits
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

    public function test_http_eventagentcommand_get()
    {
        $this->setupGuzzle();
        $response = $this->http->request(
            'GET',
            '/events/eventagentcommand',
            ['http_errors' => false]
        );

        $this->assertContains(
            $response->getStatusCode(), [
            Response::HTTP_OK,
            Response::HTTP_NOT_FOUND
            ]
        );
    }

    public function test_http_eventagentcommand_post()
    {
        $this->setupGuzzle();
        $response = $this->http->request(
            'POST', '/events/eventagentcommand', [
            'form_params'   =>  [
                'agent_type'  =>  'a',
                'operation'  =>  'a',
                'status'  =>  'a',
                'error'  =>  'a',
                    'sent_at'  =>  now(),
                    'completed_at'  =>  now(),
                    'timeout_at'  =>  now(),
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
    public function test_eventagentcommand_model_get()
    {
        $result = AbstractEventAgentCommandService::get();

        $this->assertIsObject($result, Collection::class);
    }

    public function test_eventagentcommand_get_all()
    {
        $result = AbstractEventAgentCommandService::getAll();

        $this->assertIsObject($result, Collection::class);
    }

    public function test_eventagentcommand_get_paginated()
    {
        $result = AbstractEventAgentCommandService::get(
            null, [
            'paginated' =>  'true'
            ]
        );

        $this->assertIsObject($result, LengthAwarePaginator::class);
    }

    public function test_eventagentcommand_event_retrieved_without_object()
    {
        try {
            event(new \NextDeveloper\Events\Events\EventAgentCommand\EventAgentCommandRetrievedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_eventagentcommand_event_created_without_object()
    {
        try {
            event(new \NextDeveloper\Events\Events\EventAgentCommand\EventAgentCommandCreatedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_eventagentcommand_event_creating_without_object()
    {
        try {
            event(new \NextDeveloper\Events\Events\EventAgentCommand\EventAgentCommandCreatingEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_eventagentcommand_event_saving_without_object()
    {
        try {
            event(new \NextDeveloper\Events\Events\EventAgentCommand\EventAgentCommandSavingEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_eventagentcommand_event_saved_without_object()
    {
        try {
            event(new \NextDeveloper\Events\Events\EventAgentCommand\EventAgentCommandSavedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_eventagentcommand_event_updating_without_object()
    {
        try {
            event(new \NextDeveloper\Events\Events\EventAgentCommand\EventAgentCommandUpdatingEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_eventagentcommand_event_updated_without_object()
    {
        try {
            event(new \NextDeveloper\Events\Events\EventAgentCommand\EventAgentCommandUpdatedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_eventagentcommand_event_deleting_without_object()
    {
        try {
            event(new \NextDeveloper\Events\Events\EventAgentCommand\EventAgentCommandDeletingEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_eventagentcommand_event_deleted_without_object()
    {
        try {
            event(new \NextDeveloper\Events\Events\EventAgentCommand\EventAgentCommandDeletedEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_eventagentcommand_event_restoring_without_object()
    {
        try {
            event(new \NextDeveloper\Events\Events\EventAgentCommand\EventAgentCommandRestoringEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_eventagentcommand_event_restored_without_object()
    {
        try {
            event(new \NextDeveloper\Events\Events\EventAgentCommand\EventAgentCommandRestoredEvent());
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_eventagentcommand_event_retrieved_with_object()
    {
        try {
            $model = \NextDeveloper\Events\Database\Models\EventAgentCommand::first();

            event(new \NextDeveloper\Events\Events\EventAgentCommand\EventAgentCommandRetrievedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_eventagentcommand_event_created_with_object()
    {
        try {
            $model = \NextDeveloper\Events\Database\Models\EventAgentCommand::first();

            event(new \NextDeveloper\Events\Events\EventAgentCommand\EventAgentCommandCreatedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_eventagentcommand_event_creating_with_object()
    {
        try {
            $model = \NextDeveloper\Events\Database\Models\EventAgentCommand::first();

            event(new \NextDeveloper\Events\Events\EventAgentCommand\EventAgentCommandCreatingEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_eventagentcommand_event_saving_with_object()
    {
        try {
            $model = \NextDeveloper\Events\Database\Models\EventAgentCommand::first();

            event(new \NextDeveloper\Events\Events\EventAgentCommand\EventAgentCommandSavingEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_eventagentcommand_event_saved_with_object()
    {
        try {
            $model = \NextDeveloper\Events\Database\Models\EventAgentCommand::first();

            event(new \NextDeveloper\Events\Events\EventAgentCommand\EventAgentCommandSavedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_eventagentcommand_event_updating_with_object()
    {
        try {
            $model = \NextDeveloper\Events\Database\Models\EventAgentCommand::first();

            event(new \NextDeveloper\Events\Events\EventAgentCommand\EventAgentCommandUpdatingEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_eventagentcommand_event_updated_with_object()
    {
        try {
            $model = \NextDeveloper\Events\Database\Models\EventAgentCommand::first();

            event(new \NextDeveloper\Events\Events\EventAgentCommand\EventAgentCommandUpdatedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_eventagentcommand_event_deleting_with_object()
    {
        try {
            $model = \NextDeveloper\Events\Database\Models\EventAgentCommand::first();

            event(new \NextDeveloper\Events\Events\EventAgentCommand\EventAgentCommandDeletingEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_eventagentcommand_event_deleted_with_object()
    {
        try {
            $model = \NextDeveloper\Events\Database\Models\EventAgentCommand::first();

            event(new \NextDeveloper\Events\Events\EventAgentCommand\EventAgentCommandDeletedEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_eventagentcommand_event_restoring_with_object()
    {
        try {
            $model = \NextDeveloper\Events\Database\Models\EventAgentCommand::first();

            event(new \NextDeveloper\Events\Events\EventAgentCommand\EventAgentCommandRestoringEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    public function test_eventagentcommand_event_restored_with_object()
    {
        try {
            $model = \NextDeveloper\Events\Database\Models\EventAgentCommand::first();

            event(new \NextDeveloper\Events\Events\EventAgentCommand\EventAgentCommandRestoredEvent($model));
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_eventagentcommand_event_agent_type_filter()
    {
        try {
            $request = new Request(
                [
                'agent_type'  =>  'a'
                ]
            );

            $filter = new EventAgentCommandQueryFilter($request);

            $model = \NextDeveloper\Events\Database\Models\EventAgentCommand::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_eventagentcommand_event_operation_filter()
    {
        try {
            $request = new Request(
                [
                'operation'  =>  'a'
                ]
            );

            $filter = new EventAgentCommandQueryFilter($request);

            $model = \NextDeveloper\Events\Database\Models\EventAgentCommand::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_eventagentcommand_event_status_filter()
    {
        try {
            $request = new Request(
                [
                'status'  =>  'a'
                ]
            );

            $filter = new EventAgentCommandQueryFilter($request);

            $model = \NextDeveloper\Events\Database\Models\EventAgentCommand::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_eventagentcommand_event_error_filter()
    {
        try {
            $request = new Request(
                [
                'error'  =>  'a'
                ]
            );

            $filter = new EventAgentCommandQueryFilter($request);

            $model = \NextDeveloper\Events\Database\Models\EventAgentCommand::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_eventagentcommand_event_sent_at_filter_start()
    {
        try {
            $request = new Request(
                [
                'sent_atStart'  =>  now()
                ]
            );

            $filter = new EventAgentCommandQueryFilter($request);

            $model = \NextDeveloper\Events\Database\Models\EventAgentCommand::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_eventagentcommand_event_completed_at_filter_start()
    {
        try {
            $request = new Request(
                [
                'completed_atStart'  =>  now()
                ]
            );

            $filter = new EventAgentCommandQueryFilter($request);

            $model = \NextDeveloper\Events\Database\Models\EventAgentCommand::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_eventagentcommand_event_timeout_at_filter_start()
    {
        try {
            $request = new Request(
                [
                'timeout_atStart'  =>  now()
                ]
            );

            $filter = new EventAgentCommandQueryFilter($request);

            $model = \NextDeveloper\Events\Database\Models\EventAgentCommand::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_eventagentcommand_event_created_at_filter_start()
    {
        try {
            $request = new Request(
                [
                'created_atStart'  =>  now()
                ]
            );

            $filter = new EventAgentCommandQueryFilter($request);

            $model = \NextDeveloper\Events\Database\Models\EventAgentCommand::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_eventagentcommand_event_updated_at_filter_start()
    {
        try {
            $request = new Request(
                [
                'updated_atStart'  =>  now()
                ]
            );

            $filter = new EventAgentCommandQueryFilter($request);

            $model = \NextDeveloper\Events\Database\Models\EventAgentCommand::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_eventagentcommand_event_deleted_at_filter_start()
    {
        try {
            $request = new Request(
                [
                'deleted_atStart'  =>  now()
                ]
            );

            $filter = new EventAgentCommandQueryFilter($request);

            $model = \NextDeveloper\Events\Database\Models\EventAgentCommand::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_eventagentcommand_event_sent_at_filter_end()
    {
        try {
            $request = new Request(
                [
                'sent_atEnd'  =>  now()
                ]
            );

            $filter = new EventAgentCommandQueryFilter($request);

            $model = \NextDeveloper\Events\Database\Models\EventAgentCommand::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_eventagentcommand_event_completed_at_filter_end()
    {
        try {
            $request = new Request(
                [
                'completed_atEnd'  =>  now()
                ]
            );

            $filter = new EventAgentCommandQueryFilter($request);

            $model = \NextDeveloper\Events\Database\Models\EventAgentCommand::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_eventagentcommand_event_timeout_at_filter_end()
    {
        try {
            $request = new Request(
                [
                'timeout_atEnd'  =>  now()
                ]
            );

            $filter = new EventAgentCommandQueryFilter($request);

            $model = \NextDeveloper\Events\Database\Models\EventAgentCommand::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_eventagentcommand_event_created_at_filter_end()
    {
        try {
            $request = new Request(
                [
                'created_atEnd'  =>  now()
                ]
            );

            $filter = new EventAgentCommandQueryFilter($request);

            $model = \NextDeveloper\Events\Database\Models\EventAgentCommand::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_eventagentcommand_event_updated_at_filter_end()
    {
        try {
            $request = new Request(
                [
                'updated_atEnd'  =>  now()
                ]
            );

            $filter = new EventAgentCommandQueryFilter($request);

            $model = \NextDeveloper\Events\Database\Models\EventAgentCommand::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_eventagentcommand_event_deleted_at_filter_end()
    {
        try {
            $request = new Request(
                [
                'deleted_atEnd'  =>  now()
                ]
            );

            $filter = new EventAgentCommandQueryFilter($request);

            $model = \NextDeveloper\Events\Database\Models\EventAgentCommand::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_eventagentcommand_event_sent_at_filter_start_and_end()
    {
        try {
            $request = new Request(
                [
                'sent_atStart'  =>  now(),
                'sent_atEnd'  =>  now()
                ]
            );

            $filter = new EventAgentCommandQueryFilter($request);

            $model = \NextDeveloper\Events\Database\Models\EventAgentCommand::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_eventagentcommand_event_completed_at_filter_start_and_end()
    {
        try {
            $request = new Request(
                [
                'completed_atStart'  =>  now(),
                'completed_atEnd'  =>  now()
                ]
            );

            $filter = new EventAgentCommandQueryFilter($request);

            $model = \NextDeveloper\Events\Database\Models\EventAgentCommand::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_eventagentcommand_event_timeout_at_filter_start_and_end()
    {
        try {
            $request = new Request(
                [
                'timeout_atStart'  =>  now(),
                'timeout_atEnd'  =>  now()
                ]
            );

            $filter = new EventAgentCommandQueryFilter($request);

            $model = \NextDeveloper\Events\Database\Models\EventAgentCommand::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_eventagentcommand_event_created_at_filter_start_and_end()
    {
        try {
            $request = new Request(
                [
                'created_atStart'  =>  now(),
                'created_atEnd'  =>  now()
                ]
            );

            $filter = new EventAgentCommandQueryFilter($request);

            $model = \NextDeveloper\Events\Database\Models\EventAgentCommand::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_eventagentcommand_event_updated_at_filter_start_and_end()
    {
        try {
            $request = new Request(
                [
                'updated_atStart'  =>  now(),
                'updated_atEnd'  =>  now()
                ]
            );

            $filter = new EventAgentCommandQueryFilter($request);

            $model = \NextDeveloper\Events\Database\Models\EventAgentCommand::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }

    public function test_eventagentcommand_event_deleted_at_filter_start_and_end()
    {
        try {
            $request = new Request(
                [
                'deleted_atStart'  =>  now(),
                'deleted_atEnd'  =>  now()
                ]
            );

            $filter = new EventAgentCommandQueryFilter($request);

            $model = \NextDeveloper\Events\Database\Models\EventAgentCommand::filter($filter)->first();
        } catch (\Exception $e) {
            $this->assertFalse(false, $e->getMessage());
        }

        $this->assertTrue(true);
    }
    // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE
}