<?php

Route::prefix('events')->group(function () {
    Route::get('/', 'EventsController@index');
    Route::get('/{events}', 'EventsController@show');
    Route::post('/', 'EventsController@store');
    Route::patch('/{events}', 'EventsController@update');
    Route::delete('/{events}', 'EventsController@destroy');

    Route::get('/{events}/tags', 'EventsController@tags');
    Route::post('/{events}/tags', 'EventsController@saveTags');
    Route::get('/{events}/{subObjects}', 'EventsController@relatedObjects');
});
