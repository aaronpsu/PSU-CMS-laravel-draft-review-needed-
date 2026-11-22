<?php

use App\Http\Controllers\CmsContentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomepageManagementController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\SiteSettingsController;
use App\Http\Middleware\LocalPrototypeOnly;
use Illuminate\Support\Facades\Route;

Route::middleware([LocalPrototypeOnly::class, 'web'])
    ->prefix('auth')
    ->controller(AuthController::class)
    ->group(function (): void {
        Route::get('/csrf', 'csrf');
        Route::get('/setup/status', 'setupStatus');
        Route::post('/setup', 'setupInitial')->middleware('cms.json');
        Route::post('/login', 'login')->middleware('cms.json');

        Route::middleware(['auth:web', 'cms.json'])->group(function (): void {
            Route::get('/me', 'me');
            Route::get('/notifications', 'notifications');
            Route::put('/notifications/{id}/read', 'markNotificationRead')->whereNumber('id');
            Route::put('/account', 'updateAccount');
            Route::put('/password', 'changePassword');
            Route::post('/logout', 'logout');
        });
    });

Route::middleware([LocalPrototypeOnly::class, 'web', 'auth:web', 'cms.json', 'cms.role:admin,editor'])
    ->group(function (): void {
    Route::middleware('cms.role:admin')->group(function (): void {
        Route::get('/site-settings', [SiteSettingsController::class, 'index']);
        Route::put('/site-settings', [SiteSettingsController::class, 'update']);
        Route::get('/users', [CmsContentController::class, 'usersIndex']);
        Route::get('/users/{id}', [CmsContentController::class, 'userShow'])->whereNumber('id');
        Route::post('/users', [CmsContentController::class, 'userStore']);
        Route::put('/users/{id}', [CmsContentController::class, 'userUpdate'])->whereNumber('id');
        Route::delete('/users/{id}', [CmsContentController::class, 'userDestroy'])->whereNumber('id');
        Route::get('/user-roles', [CmsContentController::class, 'userRolesIndex']);
        Route::put('/user-roles', [CmsContentController::class, 'userRoleUpdate']);
    });

    // Homepage Management
    Route::prefix('homepage')->controller(HomepageManagementController::class)->group(function (): void {
        Route::get('/', 'index');
        Route::get('/settings', 'getSettings');
        Route::put('/settings', 'saveSettings');
        Route::get('/banners', 'getBanners');
        Route::post('/banners', 'addBanner');
        Route::put('/banners/{id}', 'updateBanner');
        Route::delete('/banners/{id}', 'deleteBanner');
        Route::get('/content/{type}', 'getContentOptions');
        Route::get('/featured', 'getFeaturedItems');
        Route::put('/featured', 'saveFeaturedItems');
    });
    Route::get('/health', [CmsContentController::class, 'health']);
    Route::get('/options', [CmsContentController::class, 'options']);
    Route::get('/dashboard', DashboardController::class);

    Route::get('/news', [CmsContentController::class, 'newsIndex']);
    Route::get('/news/{id}', [CmsContentController::class, 'newsShow'])->whereNumber('id');
    Route::post('/news', [CmsContentController::class, 'newsStore']);
    Route::put('/news/{id}', [CmsContentController::class, 'newsUpdate'])->whereNumber('id');
    Route::delete('/news/{id}', [CmsContentController::class, 'newsDestroy'])->whereNumber('id');

    Route::get('/announcements', [CmsContentController::class, 'announcementsIndex']);
    Route::get('/announcements/{id}', [CmsContentController::class, 'announcementShow'])->whereNumber('id');
    Route::post('/announcements', [CmsContentController::class, 'announcementStore']);
    Route::put('/announcements/{id}', [CmsContentController::class, 'announcementUpdate'])->whereNumber('id');
    Route::delete('/announcements/{id}', [CmsContentController::class, 'announcementDestroy'])->whereNumber('id');

    Route::get('/events', [CmsContentController::class, 'eventsIndex']);
    Route::get('/events/{id}', [CmsContentController::class, 'eventShow'])->whereNumber('id');
    Route::post('/events', [CmsContentController::class, 'eventStore']);
    Route::put('/events/{id}', [CmsContentController::class, 'eventUpdate'])->whereNumber('id');
    Route::delete('/events/{id}', [CmsContentController::class, 'eventDestroy'])->whereNumber('id');

    Route::get('/event-categories', [CmsContentController::class, 'eventCategoriesIndex']);
    Route::post('/event-categories', [CmsContentController::class, 'eventCategoryStore']);
    Route::put('/event-categories/{id}', [CmsContentController::class, 'eventCategoryUpdate'])->whereNumber('id');
    Route::delete('/event-categories/{id}', [CmsContentController::class, 'eventCategoryDestroy'])->whereNumber('id');

    Route::get('/news-categories', [CmsContentController::class, 'newsCategoriesIndex']);
    Route::post('/news-categories', [CmsContentController::class, 'newsCategoryStore']);
    Route::put('/news-categories/{id}', [CmsContentController::class, 'newsCategoryUpdate'])->whereNumber('id');
    Route::delete('/news-categories/{id}', [CmsContentController::class, 'newsCategoryDestroy'])->whereNumber('id');

    Route::get('/announcement-categories', [CmsContentController::class, 'announcementCategoriesIndex']);
    Route::post('/announcement-categories', [CmsContentController::class, 'announcementCategoryStore']);
    Route::put('/announcement-categories/{id}', [CmsContentController::class, 'announcementCategoryUpdate'])->whereNumber('id');
    Route::delete('/announcement-categories/{id}', [CmsContentController::class, 'announcementCategoryDestroy'])->whereNumber('id');

    Route::get('/campuses', [CmsContentController::class, 'campusesIndex']);
    Route::post('/campuses', [CmsContentController::class, 'campusStore']);
    Route::put('/campuses/{id}', [CmsContentController::class, 'campusUpdate'])->whereNumber('id');
    Route::delete('/campuses/{id}', [CmsContentController::class, 'campusDestroy'])->whereNumber('id');

    Route::get('/media', [CmsContentController::class, 'mediaIndex']);
    Route::post('/media', [CmsContentController::class, 'mediaStore']);
    Route::put('/media/{id}', [CmsContentController::class, 'mediaUpdate'])->whereNumber('id');
    Route::delete('/media/{id}', [CmsContentController::class, 'mediaDestroy'])->whereNumber('id');

    Route::get('/documents', [CmsContentController::class, 'documentsIndex']);
    Route::post('/documents', [CmsContentController::class, 'documentStore']);
    Route::put('/documents/{id}', [CmsContentController::class, 'documentUpdate'])->whereNumber('id');
    Route::delete('/documents/{id}', [CmsContentController::class, 'documentDestroy'])->whereNumber('id');

    Route::get('/departments', [CmsContentController::class, 'departmentsIndex']);
    Route::get('/departments/{id}', [CmsContentController::class, 'departmentShow'])->whereNumber('id');
    Route::post('/departments', [CmsContentController::class, 'departmentStore']);
    Route::put('/departments/{id}', [CmsContentController::class, 'departmentUpdate'])->whereNumber('id');
    Route::delete('/departments/{id}', [CmsContentController::class, 'departmentDestroy'])->whereNumber('id');

    Route::get('/colleges', [CmsContentController::class, 'collegesIndex']);
    Route::get('/colleges/{id}', [CmsContentController::class, 'collegeShow'])->whereNumber('id');
    Route::post('/colleges', [CmsContentController::class, 'collegeStore']);
    Route::put('/colleges/{id}', [CmsContentController::class, 'collegeUpdate'])->whereNumber('id');
    Route::delete('/colleges/{id}', [CmsContentController::class, 'collegeDestroy'])->whereNumber('id');

    Route::get('/programs', [CmsContentController::class, 'programsIndex']);
    Route::get('/programs/{id}', [CmsContentController::class, 'programShow'])->whereNumber('id');
    Route::post('/programs', [CmsContentController::class, 'programStore']);
    Route::put('/programs/{id}', [CmsContentController::class, 'programUpdate'])->whereNumber('id');
    Route::delete('/programs/{id}', [CmsContentController::class, 'programDestroy'])->whereNumber('id');

    Route::get('/faculty', [CmsContentController::class, 'facultyIndex']);
    Route::get('/faculty/{id}', [CmsContentController::class, 'facultyShow'])->whereNumber('id');
    Route::post('/faculty', [CmsContentController::class, 'facultyStore']);
    Route::put('/faculty/{id}', [CmsContentController::class, 'facultyUpdate'])->whereNumber('id');
    Route::delete('/faculty/{id}', [CmsContentController::class, 'facultyDestroy'])->whereNumber('id');

    Route::get('/media/{id}', [CmsContentController::class, 'mediaShow'])->whereNumber('id')->name('cms.media');
    Route::get('/documents/{id}', [CmsContentController::class, 'documentShow'])->whereNumber('id')->name('cms.document');
});

// Public Homepage data
Route::get('/homepage/public', [HomepageManagementController::class, 'publicHomepage']);


