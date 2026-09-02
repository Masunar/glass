<?php

declare(strict_types=1);

namespace Salvon\Mail;

use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;

class Mailer
{
    protected string $template = '';

    public function __construct(
        protected array $data = [],
        ?string $template = null,
    ) {
        if (!empty($template)) {
            $this->template = $template;
        }
    }

    public static function send(
        array $data = [],
        ?string $template = null,
        bool $enqueue = true,
        ?callable $callback = null,
    ): void {
        self::factory($data, $template)->dispatch($enqueue, $callback);
    }

    public static function factory(
        array $data = [],
        ?string $template = null,
    ): self {
        $className = static::class;
        return new $className($data, $template);
    }

    public function dispatch(bool $enqueue = true, ?callable $callback = null): void
    {
        if ($enqueue) {
            dispatch(fn() => $this->sendMessage($callback));
            return;
        }

        $this->sendMessage($callback);
    }

    protected function sendMessage(?callable $callback = null): void
    {
        if (empty($this->template)) {
            throw new \Exception('Tried to use Mailer with empty template name.');
        }

        $callback = !empty($callback) ? $callback : function (Message $message): void {
            $this->message($message);
        };

        Mail::send(
            $this->template,
            $this->data,
            fn(Message $message) => $callback($message),
        );
    }

    protected function message(Message $message): void
    {
        throw new \Exception('Tried to use Mailer without message method declaration.');
    }
}
