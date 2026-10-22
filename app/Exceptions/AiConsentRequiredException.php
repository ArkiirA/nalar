<?php

namespace App\Exceptions;

use Exception;

class AiConsentRequiredException extends Exception
{
    protected $message = 'External AI processing consent has not been granted for this project.';
}
