<?php

namespace App\Models\Post;

use Illuminate\Database\Eloquent\Model;

class PostStatus extends Model
{
    protected $fillable = [
        'title',
        'alias',
        'for_create',
    ];

    public $timestamps = false;

    protected $casts = [
        'for_create' => 'boolean',
    ];

    public function posts()
    {
        return $this->hasMany(Post::class, 'status_id');
    }
}
