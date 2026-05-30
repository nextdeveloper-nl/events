<?php

Route::prefix('events')->group(
    function () {
        Route::prefix('available')->group(
            function () {
                Route::get('/', 'Available\AvailableController@index');
                Route::get('/actions', 'Available\AvailableController@getActions');

                Route::get('{event_available}/tags ', 'Available\AvailableController@tags');
                Route::post('{event_available}/tags ', 'Available\AvailableController@saveTags');
                Route::get('{event_available}/addresses ', 'Available\AvailableController@addresses');
                Route::post('{event_available}/addresses ', 'Available\AvailableController@saveAddresses');

                Route::get('/{event_available}/{subObjects}', 'Available\AvailableController@relatedObjects');
                Route::get('/{event_available}', 'Available\AvailableController@show');

                Route::post('/', 'Available\AvailableController@store');
                Route::post('/{event_available}/do/{action}', 'Available\AvailableController@doAction');

                Route::patch('/{event_available}', 'Available\AvailableController@update');
                Route::delete('/{event_available}', 'Available\AvailableController@destroy');
            }
        );

        Route::prefix('listeners')->group(
            function () {
                Route::get('/', 'Listeners\ListenersController@index');
                Route::get('/actions', 'Listeners\ListenersController@getActions');

                Route::get('{event_listeners}/tags ', 'Listeners\ListenersController@tags');
                Route::post('{event_listeners}/tags ', 'Listeners\ListenersController@saveTags');
                Route::get('{event_listeners}/addresses ', 'Listeners\ListenersController@addresses');
                Route::post('{event_listeners}/addresses ', 'Listeners\ListenersController@saveAddresses');

                Route::get('/{event_listeners}/{subObjects}', 'Listeners\ListenersController@relatedObjects');
                Route::get('/{event_listeners}', 'Listeners\ListenersController@show');

                Route::post('/', 'Listeners\ListenersController@store');
                Route::post('/{event_listeners}/do/{action}', 'Listeners\ListenersController@doAction');

                Route::patch('/{event_listeners}', 'Listeners\ListenersController@update');
                Route::delete('/{event_listeners}', 'Listeners\ListenersController@destroy');
            }
        );

        Route::prefix('agent-commands')->group(
            function () {
                Route::get('/', 'AgentCommands\AgentCommandsController@index');
                Route::get('/actions', 'AgentCommands\AgentCommandsController@getActions');

                Route::get('{event_agent_commands}/tags ', 'AgentCommands\AgentCommandsController@tags');
                Route::post('{event_agent_commands}/tags ', 'AgentCommands\AgentCommandsController@saveTags');
                Route::get('{event_agent_commands}/addresses ', 'AgentCommands\AgentCommandsController@addresses');
                Route::post('{event_agent_commands}/addresses ', 'AgentCommands\AgentCommandsController@saveAddresses');

                Route::get('/{event_agent_commands}/{subObjects}', 'AgentCommands\AgentCommandsController@relatedObjects');
                Route::get('/{event_agent_commands}', 'AgentCommands\AgentCommandsController@show');

                Route::post('/', 'AgentCommands\AgentCommandsController@store');
                Route::post('/{event_agent_commands}/do/{action}', 'AgentCommands\AgentCommandsController@doAction');

                Route::patch('/{event_agent_commands}', 'AgentCommands\AgentCommandsController@update');
                Route::delete('/{event_agent_commands}', 'AgentCommands\AgentCommandsController@destroy');
            }
        );

        // EDIT AFTER HERE - WARNING: ABOVE THIS LINE MAY BE REGENERATED AND YOU MAY LOSE CODE

















    }
);












