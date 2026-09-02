<?php

declare(strict_types=1);

namespace App\Models;

use App\Enum\MfaType;
use Carbon\Carbon;
use Salvon\Model\Dateable;

/**
 * @property int $user_id
 * @property User $user
 * @property MfaType|null $type
 * @property string|null $code
 * @property string|null $secret
 * @property string|null $pending_value
 * @property string|null $recovery_key
 * @property bool $is_active
 * @property Carbon $code_last_issued_at;
 */
class UserMfa extends Dateable
{
    protected $table = 'user_mfa';

    protected $primaryKey = 'user_id';

    protected $fillable = [
        'user_id',
        'is_active',
        'type',
        'code',
        'secret',
        'pending_value',
        'recovery_key',
        'code_last_issued_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => MfaType::class,
            'code_last_issued_at' => 'datetime',
        ];
    }
}
