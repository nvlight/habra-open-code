<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookmarkController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\CompanyController;
use App\Http\Controllers\Api\FeedController;
use App\Http\Controllers\Api\HubController;
use App\Http\Controllers\Api\PublicationCommentController;
use App\Http\Controllers\Api\PublicationController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VoteController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
});

Route::get('users', [UserController::class, 'index']);
Route::get('users/{login}', [UserController::class, 'show']);
Route::get('users/{login}/publications', [UserController::class, 'publications']);
Route::get('users/{login}/comments', [UserController::class, 'comments']);
Route::get('users/{login}/followers', [UserController::class, 'followers']);
Route::get('users/{login}/following', [UserController::class, 'following']);

Route::get('hubs', [HubController::class, 'index']);
Route::get('hubs/{alias}', [HubController::class, 'show']);
Route::get('hubs/{alias}/publications', [HubController::class, 'publications']);

Route::get('companies', [CompanyController::class, 'index']);
Route::get('companies/{slug}', [CompanyController::class, 'show']);
Route::get('companies/{slug}/publications', [CompanyController::class, 'publications']);
Route::get('companies/{slug}/employees', [CompanyController::class, 'employees']);

Route::get('publications', [PublicationController::class, 'index']);
Route::get('publications/{publication}', [PublicationController::class, 'show']);

Route::get('publications/{publication}/comments', [PublicationCommentController::class, 'index']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::prefix('auth')->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout']);
    });

    Route::get('me', [AuthController::class, 'me']);
    Route::put('profile', [UserController::class, 'updateProfile']);

    Route::post('publications', [PublicationController::class, 'store']);
    Route::put('publications/{publication}', [PublicationController::class, 'update']);
    Route::patch('publications/{publication}', [PublicationController::class, 'update']);
    Route::delete('publications/{publication}', [PublicationController::class, 'destroy']);
    Route::post('publications/{publication}/publish', [PublicationController::class, 'publish']);

    Route::post('publications/{publication}/comments', [PublicationCommentController::class, 'store']);
    Route::delete('comments/{comment}', [CommentController::class, 'destroy']);

    Route::post('publications/{publication}/vote', [VoteController::class, 'votePublication']);
    Route::post('comments/{comment}/vote', [VoteController::class, 'voteComment']);
    Route::post('users/{user}/karma', [VoteController::class, 'voteUser']);

    Route::get('bookmarks', [BookmarkController::class, 'index']);
    Route::post('publications/{publication}/bookmark', [BookmarkController::class, 'store']);
    Route::delete('publications/{publication}/bookmark', [BookmarkController::class, 'destroy']);

    Route::get('subscriptions', [SubscriptionController::class, 'index']);
    Route::post('subscriptions/{type}/{key}', [SubscriptionController::class, 'subscribe']);
    Route::delete('subscriptions/{type}/{key}', [SubscriptionController::class, 'unsubscribe']);

    Route::get('feed', FeedController::class);
});
