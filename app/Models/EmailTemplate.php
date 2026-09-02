<?php

declare(strict_types=1);

namespace App\Models;

use Salvon\Model\Dateable;
use App\Enum\EmailTemplateCode;

/**
 * @property string $code
 * @property string $name
 * @property string $content
 * @property array $variables
 */
class EmailTemplate extends Dateable
{
    protected $table = 'email_templates';

    protected $fillable = [
        'name',
        'content',
        'variables',
    ];

    protected function casts(): array
    {
        return [
            'variables' => 'array',
        ];
    }

    public static function findByCode(EmailTemplateCode $code): ?self
    {
        /** @var self|null */
        return self::query()->where('code', $code->value)->first();
    }
}
