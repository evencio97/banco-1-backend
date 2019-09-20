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

Route::group(['prefix' => 'account'/*, 'middleware' => 'auth:api'*/], function () {
    Route::post('', 'AccountController@create');
    Route::post('update', 'AccountController@update');
    Route::post('deposit', 'AccountController@deposit');
    Route::post('transfer/same-bank', 'AccountController@transferSameBank');
    Route::get('balance', 'AccountController@getAccountBalance');
    Route::get('balances', 'AccountController@getAccountsBalance');
    Route::post('balance/admin', 'AccountController@getAccountsBalanceAdmin');
    Route::get('balance/admin/all', 'AccountController@getAllAccountsBalanceAdmin');
    Route::post('moves', 'AccountController@getAccountMoves');
});

Route::group(['prefix' => 'transfer'/*, 'middleware' => 'auth:api'*/], function () {
    Route::post('same-bank', 'AccountController@transferSameBank');
});

Route::group(['prefix' => 'bill'/*, 'middleware' => 'auth:api'*/], function () {
    Route::post('', 'BillsController@create');
    Route::post('update', 'BillsController@update');
    Route::post('pay', 'BillsController@payBill');
    Route::get('', 'BillsController@getBills');
    Route::get('pay', 'BillsController@getPayBills');
    Route::get('open', 'BillsController@getOpenBills');
    Route::post('expired', 'BillsController@getExpBills');
});
