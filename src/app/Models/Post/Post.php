<?php

namespace App\Models\Post;

use App\Models\PostComment\PostComment;
use App\Models\User\User;
use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Model;
use Stevebauman\Purify\Casts\PurifyHtmlOnGet;

class Post extends Model
{
    use Filterable;

    protected $fillable = [
        'user_id',
        'title',
        'preview',
        'content',
        'status_id',
    ];

    protected $casts = [
        'preview' => PurifyHtmlOnGet::class,
        'content' => PurifyHtmlOnGet::class,
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function status()
    {
        return $this->belongsTo(PostStatus::class, 'status_id');
    }

    public function views()
    {
        return $this->hasMany(PostView::class, 'post_id');
    }

    public function comments()
    {
        return $this->hasMany(PostComment::class, 'post_id');
    }
}
