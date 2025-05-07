<?php

use App\Http\Controllers\App\User\ProfileController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

//Route::get('/welcome', function () {
//    return Inertia::render('Welcome', [
//        'canLogin' => Route::has('login'),
//        'canRegister' => Route::has('register'),
//        'laravelVersion' => Application::VERSION,
//        'phpVersion' => PHP_VERSION,
//    ]);
//});

Route::get('/create-storage-link', function() {
    if (!file_exists(public_path('storage'))) {
        Artisan::call('storage:link');
        return response()->json([
            'message' => 'Storage link created successfully'
        ]);
    }

    return response()->json([
        'message' => 'Уже есть'
    ]);
}); // Защитите роут если нужно

// Pages menu
Route::get('/', [\App\Http\Controllers\App\IndexController::class, 'index'])->name('index');
Route::get('/grammar', [\App\Http\Controllers\App\GrammarController::class, 'index'])->name('grammar');
Route::get('/grammar/item/{id}', [\App\Http\Controllers\App\GrammarController::class, 'show'])->name('grammar.show');
Route::get('/lyrics', [\App\Http\Controllers\App\LyricsController::class, 'index'])->name('lyrics');
Route::get('/lyrics/item/{id}', [\App\Http\Controllers\App\LyricsController::class, 'show'])->name('lyrics.show');
Route::get('/lessons', [\App\Http\Controllers\App\LessonsController::class, 'index'])->name('lessons');
Route::get('/lessons/item/{id}', [\App\Http\Controllers\App\LessonsController::class, 'show'])->name('lesson.show');
Route::get('/dictionary', [\App\Http\Controllers\App\DictionaryController::class, 'index'])->name('dictionary');
Route::get('/dictionary/word/{id}', [\App\Http\Controllers\App\DictionaryController::class, 'show'])->name('dictionary.show');
Route::get('/info/terms-user', [\App\Http\Controllers\App\Info\InfoController::class, 'termsUser'])->name('info.terms-user');
Route::get('/privacy-policy', [\App\Http\Controllers\App\Info\InfoController::class, 'privacyPolicy'])->name('info.privacy-policy');

// Search
Route::post('/search', [\App\Http\Controllers\App\SearchController::class, 'searchAll']);

// Search song
Route::get('song/search-by-artist-and-title', [\App\Http\Controllers\App\Song\SongController::class, 'searchByArtistAndTitle']);
Route::get('song/search-hints', [\App\Http\Controllers\App\Song\SongController::class, 'searchHints']);
Route::get('song/show/{id}', [\App\Http\Controllers\App\Song\SongController::class, 'show']);
Route::get('song/search', [\App\Http\Controllers\App\Song\SongController::class, 'search'])->name('song.search');

// Search  word
Route::get('word/learning-write', [\App\Http\Controllers\App\Word\WordController::class, 'randomList']);
Route::get('word/test-yourself', [\App\Http\Controllers\App\Word\WordController::class, 'testYourSelf']);
Route::get('word/search', [\App\Http\Controllers\App\Word\WordController::class, 'search'])->name('word.search');

// User
Route::get('/user/show/{id}', [\App\Http\Controllers\App\User\UserController::class, 'show'])->name('user.show');

// Post
Route::get('/post/show/{id}', [\App\Http\Controllers\App\PostController::class, 'show'])->name('post.show');

// widgets (используется в мобильной версии)
Route::get('/widget/player', [\App\Http\Controllers\App\Widget\WidgetController::class, 'player'])->name('widget.player');
Route::get('/widget/learning-write', [\App\Http\Controllers\App\Widget\WidgetController::class, 'learningWrite'])->name('widget.learning-write');
Route::get('/widget/check-yourself', [\App\Http\Controllers\App\Widget\WidgetController::class, 'checkYourself'])->name('widget.check-yourself');

//Route::get('/dashboard', function () {
//    return Inertia::render('Dashboard');
//})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    // User
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::post('profile/avatar-upload', [ProfileController::class, 'uploadAvatar']);
    Route::post('profile/avatar-remove', [ProfileController::class, 'removeAvatar']);
    Route::get('/users', [\App\Http\Controllers\App\User\UserController::class, 'index'])->name('users');
    Route::get('/user/posts', [\App\Http\Controllers\App\User\UserPostController::class, 'index'])->name('user.posts');
    Route::get('/user/post/edit/{id}', [\App\Http\Controllers\App\User\UserPostController::class, 'edit'])->name('user.post.edit');
    Route::post('/user/post/update/{id}', [\App\Http\Controllers\App\User\UserPostController::class, 'update'])->middleware(['is-user-ban'])->name('user.post.update');

    // Post
    Route::get('/post/create', [\App\Http\Controllers\App\PostController::class, 'create'])->name('post.create');
    Route::post('/post/store', [\App\Http\Controllers\App\PostController::class, 'store'])->middleware(['is-user-ban'])->name('post.store');
    Route::get('/post/edit/{id}', [\App\Http\Controllers\App\PostController::class, 'edit'])->name('post.edit');
    Route::post('/post/update/{id}', [\App\Http\Controllers\App\PostController::class, 'update'])->middleware(['is-user-ban'])->name('post.update');
    Route::post('/post-comment/store', [\App\Http\Controllers\App\PostCommentController::class, 'store'])->middleware(['is-user-ban'])->name('post-comment.store');
});

// Admin
Route::middleware(['auth', 'verified', 'is-admin'])->group(function () {
    Route::get('/admin', [\App\Http\Controllers\Admin\IndexController::class, 'index'])->name('admin.index');

    // Post
    Route::get('/admin/posts', [\App\Http\Controllers\Admin\Post\PostController::class, 'index'])->name('admin.posts');
    Route::post('/admin/post/update-status/{id}', [\App\Http\Controllers\Admin\Post\PostController::class, 'updateStatus'])->name('admin.post.update-status');

    // Post status
    Route::get('/admin/post-statuses', [\App\Http\Controllers\Admin\Post\PostStatusController::class, 'index'])->name('admin.posts-statuses');
    Route::get('/admin/post-status/create', [\App\Http\Controllers\Admin\Post\PostStatusController::class, 'create'])->name('admin.post-status.create');
    Route::post('/admin/post-status/store', [\App\Http\Controllers\Admin\Post\PostStatusController::class, 'store'])->name('admin.post-status.store');
    Route::get('/admin/post-status/edit/{id}', [\App\Http\Controllers\Admin\Post\PostStatusController::class, 'edit'])->name('admin.post-status.edit');
    Route::post('/admin/post-status/update/{id}', [\App\Http\Controllers\Admin\Post\PostStatusController::class, 'update'])->name('admin.post-status.update');
    Route::post('/admin/post-status/delete/{id}', [\App\Http\Controllers\Admin\Post\PostStatusController::class, 'delete'])->name('admin.post-status.delete');

    // Post comment
    Route::get('/admin/posts-comments', [\App\Http\Controllers\Admin\PostComment\PostCommentController::class, 'index'])->name('admin.posts-comments');
    Route::post('/admin/post-comment/toggle-show/{id}', [\App\Http\Controllers\Admin\PostComment\PostCommentController::class, 'toggleShow'])->name('admin.posts-comment.toggle-show');

    // Transcription
    Route::get('/admin/transcription', [\App\Http\Controllers\Admin\Transcription\TranscriptionController::class, 'index'])->name('admin.transcription');
    Route::post('/admin/transcription/train', [\App\Http\Controllers\Admin\Transcription\TranscriptionController::class, 'train'])->name('admin.transcription.train');
    Route::post('admin/transcription/transcribe', [\App\Http\Controllers\Admin\Transcription\TranscriptionController::class, 'transcribe'])->name('admin.transcription.transcribe');

    // Proverbs
    Route::get('/admin/proverbs', [\App\Http\Controllers\Admin\Proverb\ProverbController::class, 'index'])->name('admin.proverbs');
    Route::get('/admin/proverb/create', [\App\Http\Controllers\Admin\Proverb\ProverbController::class, 'create'])->name('admin.proverb.create');
    Route::post('/admin/proverb/store', [\App\Http\Controllers\Admin\Proverb\ProverbController::class, 'store'])->name('admin.proverb.store');
    Route::get('/admin/proverb/edit/{id}', [\App\Http\Controllers\Admin\Proverb\ProverbController::class, 'edit'])->name('admin.proverb.edit');
    Route::post('/admin/proverb/update/{id}', [\App\Http\Controllers\Admin\Proverb\ProverbController::class, 'update'])->name('admin.proverb.update');
//    Route::post('/admin/proverb/delete/{id}', [\App\Http\Controllers\Admin\Proverb\ProverbController::class, 'delete'])->name('admin.proverb.delete');

    Route::get('/admin/songs', [\App\Http\Controllers\Admin\Song\SongController::class, 'index'])->name('admin.songs');
    Route::get('/admin/song/create', [\App\Http\Controllers\Admin\Song\SongController::class, 'create'])->name('admin.song.create');
    Route::post('/admin/song/store', [\App\Http\Controllers\Admin\Song\SongController::class, 'store'])->name('admin.song.store');
    Route::get('/admin/song/edit/{id}', [\App\Http\Controllers\Admin\Song\SongController::class, 'edit'])->name('admin.song.edit');
    Route::post('/admin/song/update/{id}', [\App\Http\Controllers\Admin\Song\SongController::class, 'update'])->name('admin.song.update');
//    Route::post('/admin/song/delete/{id}', [\App\Http\Controllers\Admin\Song\SongController::class, 'delete'])->name('admin.song.delete');

    // dictionary (словарь, слова)
    Route::get('/admin/dictionary/search', [\App\Http\Controllers\Admin\Dictionary\DictionaryController::class, 'search'])->name('admin.dictionary.search');
    Route::get('/admin/dictionary', [\App\Http\Controllers\Admin\Dictionary\DictionaryController::class, 'index'])->name('admin.dictionary');
    Route::get('/admin/dictionary/create', [\App\Http\Controllers\Admin\Dictionary\DictionaryController::class, 'create'])->name('admin.dictionary.create');
    Route::post('/admin/dictionary/store', [\App\Http\Controllers\Admin\Dictionary\DictionaryController::class, 'store'])->name('admin.dictionary.store');
    Route::get('/admin/dictionary/edit/{id}', [\App\Http\Controllers\Admin\Dictionary\DictionaryController::class, 'edit'])->name('admin.dictionary.edit');
    Route::post('/admin/dictionary/update/{id}', [\App\Http\Controllers\Admin\Dictionary\DictionaryController::class, 'update'])->name('admin.dictionary.update');
    Route::post('/admin/dictionary/delete/{id}', [\App\Http\Controllers\Admin\Dictionary\DictionaryController::class, 'delete'])->name('admin.dictionary.delete');

    // lessons
    Route::get('/admin/lessons', [\App\Http\Controllers\Admin\Lesson\LessonController::class, 'index'])->name('admin.lessons');
    Route::get('/admin/lessons/create', [\App\Http\Controllers\Admin\Lesson\LessonController::class, 'create'])->name('admin.lessons.create');
    Route::post('/admin/lessons/store', [\App\Http\Controllers\Admin\Lesson\LessonController::class, 'store'])->name('admin.lessons.store');
    Route::get('/admin/lessons/edit/{id}', [\App\Http\Controllers\Admin\Lesson\LessonController::class, 'edit'])->name('admin.lessons.edit');
    Route::post('/admin/lessons/update/{id}', [\App\Http\Controllers\Admin\Lesson\LessonController::class, 'update'])->name('admin.lessons.update');
    Route::post('/admin/lessons/delete/{id}', [\App\Http\Controllers\Admin\Lesson\LessonController::class, 'delete'])->name('admin.lessons.delete');

    // grammars
    Route::get('/admin/grammars', [\App\Http\Controllers\Admin\Grammar\GrammarController::class, 'index'])->name('admin.grammars');
    Route::get('/admin/grammars/create', [\App\Http\Controllers\Admin\Grammar\GrammarController::class, 'create'])->name('admin.grammars.create');
    Route::post('/admin/grammars/store', [\App\Http\Controllers\Admin\Grammar\GrammarController::class, 'store'])->name('admin.grammars.store');
    Route::get('/admin/grammars/edit/{id}', [\App\Http\Controllers\Admin\Grammar\GrammarController::class, 'edit'])->name('admin.grammars.edit');
    Route::post('/admin/grammars/update/{id}', [\App\Http\Controllers\Admin\Grammar\GrammarController::class, 'update'])->name('admin.grammars.update');
    Route::post('/admin/grammars/delete/{id}', [\App\Http\Controllers\Admin\Grammar\GrammarController::class, 'delete'])->name('admin.grammars.delete');

    // users
    Route::get('/admin/users', [\App\Http\Controllers\Admin\User\UserController::class, 'index'])->name('admin.users');
    Route::post('/admin/user/ban', [\App\Http\Controllers\Admin\User\UserController::class, 'ban'])->name('admin.users.ban');

    // referers
    Route::get('/admin/referers', [\App\Http\Controllers\Admin\UserReferer\UserRefererController::class, 'index'])->name('admin.referers');

});

require __DIR__.'/auth.php';
