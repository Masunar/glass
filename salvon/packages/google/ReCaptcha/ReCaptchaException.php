<?php

declare(strict_types=1);

namespace Salvon\Google\ReCaptcha;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class ReCaptchaException extends BadRequestHttpException
{
    protected $message = 'backend.invalid_recaptcha_response';
}
