<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\InviteRedemptionController;
use App\Http\Controllers\BackupDownloadController;
use App\Http\Controllers\InstanceManifestController;
use App\Http\Controllers\Listen\AlbumController;
use App\Http\Controllers\Listen\ArtistController;
use App\Http\Controllers\Listen\CatalogueController;
use App\Http\Controllers\Listen\HomeController;
use App\Http\Controllers\Listen\LegacyMobileController;
use App\Http\Controllers\Listen\LibraryController;
use App\Http\Controllers\Listen\LocaleController;
use App\Http\Controllers\Listen\ListeningHistoryController;
use App\Http\Controllers\Listen\PlaybackSourceController;
use App\Http\Controllers\Listen\PlaylistController;
use App\Http\Controllers\Listen\SearchController;
use App\Http\Controllers\Listen\UserSettingsController;
use App\Http\Controllers\Listen\WebTrackAudioController;
use Illuminate\Support\Facades\Route;

Route::get('/.well-known/indie-house', InstanceManifestController::class)
    ->name('instance.manifest');

Route::get('/legacy', [LegacyMobileController::class, 'index'])->name('legacy.index');
Route::post('/legacy/login', [LegacyMobileController::class, 'login'])->middleware('throttle:web-login')->name('legacy.login');
Route::post('/legacy/logout', [LegacyMobileController::class, 'logout'])->name('legacy.logout');
Route::get('/legacy/tracks/{track:public_id}/audio', [LegacyMobileController::class, 'audio'])
    ->middleware('throttle:120,1')->name('legacy.audio');

Route::get('/', HomeController::class)->name('listen.home');
Route::get('/search', SearchController::class)->name('listen.search');
Route::get('/catalogue', CatalogueController::class)->name('listen.catalogue');
Route::get('/artists/{artist:public_id}', ArtistController::class)->name('artists.show');
Route::get('/albums/{album:public_id}', AlbumController::class)->name('albums.show');
Route::get('/tracks/{track:public_id}/source', PlaybackSourceController::class)
    ->middleware('throttle:60,1')
    ->name('tracks.source');
Route::get('/tracks/{track:public_id}/audio', WebTrackAudioController::class)
    ->middleware('throttle:240,1')
    ->name('tracks.audio');

Route::get('/playlists/{playlist:public_id}', [PlaylistController::class, 'show'])->name('playlists.show');
Route::post('/locale', LocaleController::class)->middleware('throttle:30,1')->name('locale.update');
Route::view('/terms', 'listen.legal', ['document' => 'terms'])->name('legal.terms');
Route::view('/privacy', 'listen.legal', ['document' => 'privacy'])->name('legal.privacy');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:web-login')->name('login.store');
    Route::get('/register', [AuthenticatedSessionController::class, 'createRegistration'])->name('register');
    Route::post('/register', [AuthenticatedSessionController::class, 'register'])->middleware('throttle:10,1')->name('register.store');
    Route::get('/invite/{code}', [InviteRedemptionController::class, 'show'])->name('invite.show');
    Route::post('/invite/redeem', [InviteRedemptionController::class, 'web'])->middleware('throttle:5,1')->name('invite.redeem');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/admin/backups/{backupRecord}/download', BackupDownloadController::class)
        ->name('backup.download');

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/library', LibraryController::class)->name('library.index');
    Route::get('/settings', [UserSettingsController::class, 'show'])->name('settings.show');
    Route::put('/settings/profile', [UserSettingsController::class, 'updateProfile'])->middleware('throttle:10,1')->name('settings.profile');
    Route::put('/settings/playback', [UserSettingsController::class, 'updatePlayback'])->name('settings.playback');
    Route::put('/settings/privacy', [UserSettingsController::class, 'updatePrivacy'])->name('settings.privacy');
    Route::put('/settings/password', [UserSettingsController::class, 'updatePassword'])->middleware('throttle:5,1')->name('settings.password');
    Route::delete('/settings/devices/{session}', [UserSettingsController::class, 'revokeDevice'])->name('settings.devices.revoke');
    Route::get('/settings/export', [UserSettingsController::class, 'export'])->middleware('throttle:5,1')->name('settings.export');
    Route::post('/listening-history', [ListeningHistoryController::class, 'store'])
        ->middleware('throttle:120,1')
        ->name('listening-history.store');

    Route::post('/playlists', [PlaylistController::class, 'store'])->name('playlists.store');
    Route::patch('/playlists/{playlist:public_id}', [PlaylistController::class, 'update'])->name('playlists.update');
    Route::delete('/playlists/{playlist:public_id}', [PlaylistController::class, 'destroy'])->name('playlists.destroy');
    Route::post('/playlists/{playlist:public_id}/tracks', [PlaylistController::class, 'storeTrack'])->name('playlists.tracks.store');
    Route::delete('/playlists/{playlist:public_id}/tracks/{track:public_id}', [PlaylistController::class, 'destroyTrack'])
        ->name('playlists.tracks.destroy');
});
