<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('pos')->middleware('auth:api')->group(function () {
    Route::apiResource('tables', API\TablesController::class)->only(['index', 'store', 'update', 'show']);
    Route::post('tables/{table}/merge', [API\TablesController::class, 'merge']);
    Route::post('tables/{table}/transfer', [API\TablesController::class, 'transfer']);

    Route::apiResource('orders', API\OrdersController::class)->only(['index', 'store', 'update', 'show']);
    Route::post('orders/{order}/close', [API\OrdersController::class, 'close']);
    Route::post('orders/{order}/items/{item}/void', [API\OrdersController::class, 'voidItem']);
    Route::post('orders/{order}/items/{item}/discount', [API\OrdersController::class, 'applyDiscount']);
});

/*
 * ---------------
 * Organisers
 * ---------------
 */


/*
 * ---------------
 * Events
 * ---------------
 */
Route::resource('events', API\EventsApiController::class);


/*
 * ---------------
 * Attendees
 * ---------------
 */
Route::resource('attendees', API\AttendeesApiController::class);


/*
 * ---------------
 * Orders
 * ---------------
 */

/*
 * ---------------
 * Users
 * ---------------
 */

/*
 * ---------------
 * Check-In / Check-Out
 * ---------------
 */
