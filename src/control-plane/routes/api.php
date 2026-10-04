<?php

use App\Http\Controllers\Auth\InviteRedemptionController;
use App\Http\Controllers\Auth\MobileTokenController;
use App\Http\Controllers\Media\InternalStreamAssetController;
use App\Http\Controllers\Media\TrackProcessingController;
use App\Http\Controllers\Mobile\MobileAccountController;
use App\Http\Controllers\Mobile\MobileArtworkController;
use App\Http\Controllers\Mobile\MobileCatalogueController;
use App\Http\Controllers\Mobile\MobileDevicesController;
use App\Http\Controllers\Mobile\MobileLegalController;
use App\Http\Controllers\Mobile\MobileLibraryController;
use App\Http\Controllers\Mobile\MobilePreferencesController;
use App\Http\Middleware\AuthenticateMobileAccess;
use App\Support\LegalDocument;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/mobile/auth')->group(function (): void {
    Route::post('/token', [MobileTokenController::class, 'store']);
    Route::post('/refresh', [MobileTokenController::class, 'refresh']);
    Route::post('/logout', [MobileTokenController::class, 'destroy']);
});

Route::post('v1/mobile/invites/redeem', [InviteRedemptionController::class, 'mobile'])->middleware('throttle:5,1');
Route::get('v1/mobile/legal/{document}', MobileLegalController::class)
    ->whereIn('document', LegalDocument::DOCUMENTS)
    ->middleware('throttle:60,1');

Route::prefix('v1/mobile')->middleware([AuthenticateMobileAccess::class, 'throttle:120,1'])->group(function (): void {
    Route::get('/catalogue', [MobileCatalogueController::class, 'index']);
    Route::get('/browse', [MobileCatalogueController::class, 'browse']);
    Route::get('/albums', [MobileCatalogueController::class, 'albums']);
    Route::get('/artists', [MobileCatalogueController::class, 'artists']);
    Route::get('/search', [MobileCatalogueController::class, 'search']);
    Route::get('/tracks/{track:public_id}', [MobileCatalogueController::class, 'track']);
    Route::get('/tracks/{track:public_id}/artwork', MobileArtworkController::class);
    Route::get('/artists/{artist:public_id}/artwork', [MobileArtworkController::class, 'artist']);
    Route::get('/albums/{album:public_id}/artwork', [MobileArtworkController::class, 'album']);
    Route::get('/artists/{artist:public_id}', [MobileCatalogueController::class, 'artist']);
    Route::get('/albums/{album:public_id}', [MobileCatalogueController::class, 'album']);
    Route::get('/library', [MobileLibraryController::class, 'index']);
    Route::get('/playlists', [MobileLibraryController::class, 'playlists']);
    Route::get('/playlists/{playlist:public_id}', [MobileLibraryController::class, 'playlist']);
    Route::post('/playlists', [MobileLibraryController::class, 'storePlaylist']);
    Route::patch('/playlists/{playlist:public_id}', [MobileLibraryController::class, 'updatePlaylist']);
    Route::delete('/playlists/{playlist:public_id}', [MobileLibraryController::class, 'deletePlaylist']);
    Route::post('/playlists/{playlist:public_id}/tracks', [MobileLibraryController::class, 'addTrack']);
    Route::delete('/playlists/{playlist:public_id}/tracks/{track:public_id}', [MobileLibraryController::class, 'removeTrack']);
    Route::post('/play-logs', [MobileLibraryController::class, 'storePlayLog']);
    Route::get('/devices', [MobileDevicesController::class, 'index']);
    Route::delete('/devices/{session}', [MobileDevicesController::class, 'revoke']);
    Route::get('/preferences', [MobilePreferencesController::class, 'show']);
    Route::patch('/preferences', [MobilePreferencesController::class, 'update']);
    Route::get('/account', [MobileAccountController::class, 'show']);
    Route::get('/account/avatar/{version}', [MobileArtworkController::class, 'avatar'])->where('version', '[0-9a-f]{12}');
});

Route::prefix('internal')
    ->middleware(['internal.api']) // Custom middleware check X-Internal-Key
    ->group(function () {
        Route::post('/tracks/status', [TrackProcessingController::class, 'updateStatus']);
        Route::post('/tracks/renditions', [TrackProcessingController::class, 'createRenditions']);
        Route::post('/tracks/preview', [TrackProcessingController::class, 'registerPreview']);
        Route::post('/tracks/lossless', [TrackProcessingController::class, 'registerLossless']);
        Route::get('/stream/tracks/{track:public_id}/asset', InternalStreamAssetController::class);
    });
