<?php

namespace App\Models\Learning;

use App\Support\Learning\EncodesPracticeJson;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class SessionLexeme extends Model
{
    use EncodesPracticeJson;

    protected $table = 'learning_session_lexemes';

    public $timestamps = false;

    protected $fillable = ['session_id', 'lexeme_key', 'context'];

    protected $casts = ['context' => 'array'];

    public function lexeme(): BelongsTo
    {
        return $this->belongsTo(PracticeLexeme::class, 'lexeme_key', 'lexeme_key');
    }

    public function content(): array
    {
        return ['lexeme_key' => $this->lexeme_key] + $this->context + $this->lexeme->content;
    }
}
