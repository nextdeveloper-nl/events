<?php

Route::prefix('events')->group(
    function () {
        Route::prefix('available')->group(
            function () {
                Route::get('/', 'Availables\AvailablesController@index');
                Route::get('/actions', 'Availables\AvailablesController@getActions');

                Route::get('{event_available}/tags ', 'Availables\AvailablesController@tags');
                Route::post('{event_available}/tags ', 'Availables\AvailablesController@saveTags');
                Route::get('{event_available}/addresses ', 'Availables\AvailablesController@addresses');
                Route::post('{event_available}/addresses ', 'Availables\AvailablesController@saveAddresses');

                Route::get('/{event_available}/{subObjects}', 'Availables\AvailablesController@relatedObjects');
                Route::get('/{event_available}', 'Availables\AvailablesController@show');

                Route::post('/', 'Availables\AvailablesController@store');
                Route::post('/{event_available}/do/{action}', 'Availables\AvailablesController@doAction');

                Route::patch('/{event_available}', 'Availables\AvailablesController@update');
                Route::delete('/{event_available}', 'Availables\AvailablesController@destroy');
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












