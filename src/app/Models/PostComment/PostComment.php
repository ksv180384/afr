<?php

namespace App\Models\PostComment;

use App\Models\Post\Post;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Model;
use Stevebauman\Purify\Casts\PurifyHtmlOnGet;

class PostComment extends Model
{
    protected $fillable = [
        'user_id',
        'post_id',
        'parent_post_comment_id',
        'comment',
    ];

    protected $casts = [
        'comment' => PurifyHtmlOnGet::class,
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function post()
    {
        return $this->belongsTo(Post::class, 'post_id');
    }

    public function parentComment()
    {
        return $this->belongsTo(PostComment::class, 'parent_post_comment_id');
    }
}
