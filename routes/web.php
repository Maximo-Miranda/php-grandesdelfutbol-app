<?php

use App\Http\Controllers\Api\DriveUploadController;
use App\Http\Controllers\Api\S3MultipartController;
use App\Http\Controllers\ClubController;
use App\Http\Controllers\ClubInvitationController;
use App\Http\Controllers\ClubJoinController;
use App\Http\Controllers\ClubMemberController;
use App\Http\Controllers\ClubNotificationsController;
use App\Http\Controllers\ClubSearchController;
use App\Http\Controllers\ClubSwitchController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmailVerificationCodeController;
use App\Http\Controllers\FieldController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MatchAttendanceController;
use App\Http\Controllers\MatchController;
use App\Http\Controllers\MatchEventController;
use App\Http\Controllers\MatchLifecycleController;
use App\Http\Controllers\MatchReelController;
use App\Http\Controllers\MatchVideoUploadController;
use App\Http\Controllers\PlayerCardController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\PlayerProfileController;
use App\Http\Controllers\PrivacyController;
use App\Http\Controllers\PublicMatchController;
use App\Http\Controllers\TermsController;
use App\Http\Controllers\VenueController;
use App\Http\Controllers\VideoServiceRequestController;
use App\Http\Controllers\VideoShareController;
use App\Http\Controllers\YouTubeAuthController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', HomeController::class)->name('home');
Route::get('terms', TermsController::class)->name('terms');
Route::get('privacy', PrivacyController::class)->name('privacy');

Route::get('match/{shareToken}', [PublicMatchController::class, 'show'])->name('match.public');
Route::get('video/{matchUlid}', [VideoShareController::class, 'show'])->name('video.share');
Route::get('clubs/invitations/{token}/accept', [ClubInvitationController::class, 'show'])->name('invitations.show');
Route::get('join/{slug}', [ClubJoinController::class, 'show'])->name('clubs.join');

Route::post('video-service-request', [VideoServiceRequestController::class, 'store'])
    ->middleware('throttle:3,1')
    ->name('video-service-request.store');

Route::middleware('guest')->group(function () {
    Route::get('start', function () {
        if (auth()->check()) {
            return redirect()->route('home');
        }

        return Inertia::render('auth/Start', [
            'canRegister' => Features::enabled(Features::registration()),
            'mode' => request()->query('mode', 'register'),
            'googleAuthEnabled' => config('services.google.enabled', false),
        ]);
    })->name('start');

    Route::get('auth/google', [GoogleAuthController::class, 'redirect'])->name('auth.google');
    Route::get('auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');
});

Route::middleware(['auth'])->group(function () {
    Route::post('join/{slug}', [ClubJoinController::class, 'store'])->name('clubs.join.store');
    Route::post('clubs/invitations/{token}/accept', [ClubInvitationController::class, 'accept'])->name('invitations.accept');
    Route::post('email/verify-code', [EmailVerificationCodeController::class, 'verify'])->name('verification.verify-code');
    Route::post('email/resend-code', [EmailVerificationCodeController::class, 'resend'])->name('verification.resend-code');
});

Route::middleware(['auth', 'verified'])->group(function () {

    Route::prefix('s3/multipart')->group(function () {
        Route::post('/', [S3MultipartController::class, 'create'])->name('s3.multipart.create');
        Route::get('{uploadId}', [S3MultipartController::class, 'listParts'])->name('s3.multipart.listParts');
        Route::get('{uploadId}/{partNumber}', [S3MultipartController::class, 'signPart'])->name('s3.multipart.signPart');
        Route::post('{uploadId}/complete', [S3MultipartController::class, 'complete'])->name('s3.multipart.complete');
        Route::delete('{uploadId}', [S3MultipartController::class, 'abort'])->name('s3.multipart.abort');
    });

    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::get('player-card', PlayerCardController::class)->name('player-card');
    Route::resource('clubs', ClubController::class);
    Route::get('clubs-search', ClubSearchController::class)->name('clubs.search');
    Route::post('clubs/{club}/switch', ClubSwitchController::class)->name('clubs.switch');

    Route::prefix('clubs/{club}')->name('clubs.')->group(function () {

        Route::get('invite', [ClubInvitationController::class, 'create'])->name('invitations.create');
        Route::post('invite', [ClubInvitationController::class, 'store'])->name('invitations.store');

        Route::get('members', [ClubMemberController::class, 'index'])->name('members.index');
        Route::patch('members/{member}/approve', [ClubMemberController::class, 'approve'])->name('members.approve');
        Route::delete('members/{member}/reject', [ClubMemberController::class, 'reject'])->name('members.reject');
        Route::patch('members/{member}/role', [ClubMemberController::class, 'updateRole'])->name('members.updateRole');
        Route::delete('members/{member}', [ClubMemberController::class, 'remove'])->name('members.remove');
        Route::post('leave', [ClubMemberController::class, 'leave'])->name('leave');

        Route::resource('players', PlayerController::class);

        Route::post('venues/quick-create', [VenueController::class, 'storeQuick'])->name('venues.storeQuick');
        Route::resource('venues', VenueController::class);
        Route::post('venues/{venue}/fields', [FieldController::class, 'store'])->name('venues.fields.store');
        Route::put('venues/{venue}/fields/{field}', [FieldController::class, 'update'])->name('venues.fields.update');
        Route::delete('venues/{venue}/fields/{field}', [FieldController::class, 'destroy'])->name('venues.fields.destroy');

        Route::resource('matches', MatchController::class);
        Route::get('matches/{match}/live', [MatchController::class, 'live'])->name('matches.live');
        Route::get('matches/{match}/summary', [MatchController::class, 'summary'])->name('matches.summary');
        Route::post('matches/{match}/attendance', [MatchAttendanceController::class, 'store'])->name('matches.attendance.store');
        Route::patch('matches/{match}/attendance/{attendance}', [MatchAttendanceController::class, 'update'])->name('matches.attendance.update');
        Route::delete('matches/{match}/attendance/{attendance}', [MatchAttendanceController::class, 'destroy'])->name('matches.attendance.destroy');
        Route::post('matches/{match}/auto-assign', [MatchAttendanceController::class, 'autoAssign'])->name('matches.autoAssign');

        Route::post('matches/{match}/events', [MatchEventController::class, 'store'])->name('matches.events.store');
        Route::patch('matches/{match}/events/{event}', [MatchEventController::class, 'update'])->name('matches.events.update');
        Route::put('matches/{match}/events/{event}', [MatchEventController::class, 'fullUpdate'])->name('matches.events.fullUpdate');
        Route::delete('matches/{match}/events/{event}', [MatchEventController::class, 'destroy'])->name('matches.events.destroy');

        Route::post('matches/{match}/start', [MatchLifecycleController::class, 'start'])->name('matches.start');
        Route::post('matches/{match}/complete', [MatchLifecycleController::class, 'complete'])->name('matches.complete');
        Route::post('matches/{match}/cancel', [MatchLifecycleController::class, 'cancel'])->name('matches.cancel');
        Route::post('matches/{match}/finalize-stats', [MatchLifecycleController::class, 'finalizeStats'])->name('matches.finalizeStats');

        Route::post('matches/{match}/video-upload', [MatchVideoUploadController::class, 'store'])->name('matches.videoUpload.store');
        Route::get('matches/{match}/video-upload', [MatchVideoUploadController::class, 'show'])->name('matches.videoUpload.show');
        Route::post('matches/{match}/video-upload/retry-youtube', [MatchVideoUploadController::class, 'retryYouTube'])->name('matches.videoUpload.retryYouTube');
        Route::post('matches/{match}/video-upload/share-link', [VideoShareController::class, 'generate'])->name('matches.videoUpload.shareLink');
        Route::delete('matches/{match}/video-upload', [MatchVideoUploadController::class, 'destroy'])->name('matches.videoUpload.destroy');

        Route::post('matches/{match}/drive-upload/init', [DriveUploadController::class, 'initUpload'])->name('matches.driveUpload.init');
        Route::post('drive-upload/refresh-token', [DriveUploadController::class, 'refreshToken'])->name('driveUpload.refreshToken');
        Route::post('matches/{match}/drive-upload/probe', [DriveUploadController::class, 'probeStatus'])->name('matches.driveUpload.probe');
        Route::post('matches/{match}/drive-upload/complete', [DriveUploadController::class, 'completeUpload'])->name('matches.driveUpload.complete');

        Route::post('matches/{match}/reels/generate', [MatchReelController::class, 'generate'])->name('matches.reels.generate');
        Route::post('matches/{match}/reels', [MatchReelController::class, 'store'])->name('matches.reels.store');
        Route::post('matches/{match}/reels/request', [MatchReelController::class, 'request'])->name('matches.reels.request');
        Route::post('matches/{match}/reels/request-player', [MatchReelController::class, 'requestForPlayer'])->name('matches.reels.requestForPlayer');
        Route::post('matches/{match}/reels/{reel}/approve', [MatchReelController::class, 'approve'])->name('matches.reels.approve');
        Route::delete('matches/{match}/reels/{reel}/reject', [MatchReelController::class, 'reject'])->name('matches.reels.reject');
        Route::post('matches/{match}/reels/{reel}/view', [MatchReelController::class, 'view'])->name('matches.reels.view');
        Route::delete('matches/{match}/reels/{reel}', [MatchReelController::class, 'destroy'])->name('matches.reels.destroy');

        Route::get('notifications', [ClubNotificationsController::class, 'show'])->name('notifications.show');
        Route::post('notifications/test', [ClubNotificationsController::class, 'sendTest'])->name('notifications.test');
    });

    Route::get('player-profile', [PlayerProfileController::class, 'edit'])->name('player-profile.edit');
    Route::patch('player-profile', [PlayerProfileController::class, 'update'])->name('player-profile.update');

    Route::get('admin/youtube/authorize', [YouTubeAuthController::class, 'redirect'])->name('youtube.authorize');
    Route::get('admin/youtube/callback', [YouTubeAuthController::class, 'callback'])->name('youtube.callback');
});

require __DIR__.'/settings.php';
