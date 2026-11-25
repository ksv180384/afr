<?php

namespace App\Models\Learning;

use App\Support\Learning\EncodesPracticeJson;
use Illuminate\Database\Eloquent\Model;

final class PracticeLexeme extends Model
{
    use EncodesPracticeJson;

    protected $table = 'learning_lexemes';

    protected $primaryKey = 'lexeme_key';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['lexeme_key', 'content'];

    protected $casts = ['content' => 'array'];
}
