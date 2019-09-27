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

Route::group(['prefix' => 'auth','middleware' => 'cors'], function () {
    Route::post('login', 'AuthController@login');
    Route::post('signup', 'AuthController@signup');
    Route::get('signup/activate/{token}', 'AuthController@signupActivate');
  
    Route::group(['middleware' => 'jwtAuth'], function() {
        Route::get('logout', 'AuthController@logout');
        Route::get('user', 'AuthController@user');
    });
});

Route::group(['namespace' => 'Auth', 'middleware' => 'api', 'prefix' => 'password'], function () {    
    Route::post('create', 'PasswordResetController@create');
    Route::get('find/{token}', 'PasswordResetController@find');
    Route::post('reset', 'PasswordResetController@reset');
});

Route::group(['prefix' => 'account', 'middleware' => 'jwtAuth'], function () {
    Route::post('', 'AccountController@create');
    Route::post('update', 'AccountController@update');
    // Route::post('deposit', 'AccountController@deposit');
    Route::post('transfer', 'AccountController@transferSameBank');
    Route::post('transfer/other-bank', 'AccountController@transferOtherBank');
    Route::post('receive', 'AccountController@receive');
    Route::post('one', 'AccountController@getAccount');
    Route::get('user', 'AccountController@getAccounts');
    Route::post('one/admin', 'AccountController@getAccountsAdmin');
    Route::get('all/admin', 'AccountController@getAllAccountsAdmin');
    Route::post('moves', 'AccountController@getAccountMoves');
});


Route::group(['prefix' => 'bill', 'middleware' => 'jwtAuth'], function () {
    Route::post('', 'BillsController@create');
    Route::post('update', 'BillsController@update');
    Route::post('pay', 'BillsController@payBill');
    Route::get('', 'BillsController@getBills');
    Route::get('pay', 'BillsController@getPayBills');
    Route::get('open', 'BillsController@getOpenBills');
    Route::get('expired', 'BillsController@getExpBills');
});

Route::group(['prefix' => 'tdc', 'middleware' => 'jwtAuth'], function () {
    Route::post('', 'CreditCardsController@create');
    Route::post('update', 'CreditCardsController@update');
    Route::post('update/status', 'CreditCardsController@changeStatus');
    Route::post('pay', 'CreditCardsController@payCreditCard');
    Route::post('purchase', 'CreditCardsController@purchase');
    Route::get('purchases/last', 'CreditCardsController@getLastUserPurchases');
    Route::get('', 'CreditCardsController@getCreditCards');
    Route::get('admin', 'CreditCardsController@getCreditCardsAdmin');
    Route::get('one', 'CreditCardsController@getCreditCard');
});
