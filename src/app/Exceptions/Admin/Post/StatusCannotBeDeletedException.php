<?php

namespace App\Exceptions\Admin\Post;

use Exception;

class StatusCannotBeDeletedException extends Exception
{
    protected $message = 'Этот статус не может быть удален, так как он используется постами.';
}
