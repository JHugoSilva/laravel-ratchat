<?php

use App\Http\Controllers\LoginController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::controller(LoginController::class)->group(function(){
    Route::get('login', 'index')->name('login');
    Route::get('registration', 'registration')->name('registration');
    Route::post('register', 'register')->name('register');
    Route::post('login', 'login')->name('login_action');
    Route::get('logout', 'logout')->name('logout');
    Route::get('dashboard', 'dashboard')->name('dashboard');
    Route::get('profile', 'profile')->name('profile');
    Route::post('profile', 'profile_action')->name('profile_action');
});
