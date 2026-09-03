<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use App\Mail\ResetPasswordEmail;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\NewAccessToken;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Permission\Models\Permission;
use Illuminate\Notifications\Notifiable;
use Salvon\Model\User as Authenticatable;
use Spatie\Permission\Traits\HasPermissions;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $email
 * @property string $phone
 * @property string $first_name
 * @property string $last_name
 * @property bool $is_active
 * @property int|null $location_id
 * @property Location|null $location
 * @property UserMfa|null $mfa
 * @property Carbon|null $last_login_at
 *
 * Relacje zwracaja kolekcje Eloquenta, nie tablice. Adnotacja "Role[]"
 * byla nieprawda i przechodzila tylko dopoty, dopoki nikt nie wolal na
 * niej metody kolekcji.
 *
 * @property-read Collection<int, Role> $roles
 * @property-read Collection<int, Permission> $permissions
 *
 * Salvon zamienia znaczniki czasu na sformatowany string w akcesorze
 * (CastBuiltInDates), wiec to nie sa daty. Do liczenia na nich trzeba
 * siegnac po surowa wartosc przez getRawOriginal().
 *
 * @property-read string|null $created_at
 * @property-read string|null $updated_at
 */
class User extends Authenticatable implements MustVerifyEmail
{
    use Notifiable;
    use HasApiTokens;
    use HasRoles;
    use HasPermissions;
    use SoftDeletes;

    protected $table = 'users';

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'location_id',
        'is_active',
        'password',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function activated(): bool
    {
        return $this->is_active;
    }

    public function isSuperUser(): bool
    {
        foreach ($this->roles as $role) {
            if ($role['is_superuser'] ?? false) {
                return true;
            }
        }

        return false;
    }

    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        ResetPasswordEmail::send([
            'token' => $token,
            'user' => $this->toArray(),
            'url' => frontend_route('reset-password', ['token' => $token]),
        ]);
    }

    /**
     * Token dla klientow korzystajacych z uwierzytelniania tokenem
     * (POST /api/auth/login?mode=token), w odroznieniu od domyslnego
     * trybu ciasteczkowego uzywanego przez aplikacje webowa.
     */
    public function generateAuthenticationToken(string $name = 'authentication'): NewAccessToken
    {
        return $this->createToken($name);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id', 'id');
    }

    public function mfa(): HasOne
    {
        return $this->hasOne(UserMfa::class, 'user_id', 'id');
    }

    public function hasMfa(): bool
    {
        return $this->mfa instanceof UserMfa;
    }

    public function mfaActive(): bool
    {
        return $this->hasMfa() && $this->mfa->is_active;
    }

    public function updateOrCreateMfa(array $data): UserMfa
    {
        $mfa = UserMfa::query()->updateOrCreate(
            ['user_id' => $this->id],
            $data,
        );

        $this->setRelation('mfa', $mfa);

        return $mfa;
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            // Bez rzutowania ostatnie logowanie jest zwyklym stringiem,
            // a ekran uzytkownikow liczy na nim wiek konta.
            'last_login_at' => 'datetime',
            'activation_token_expires_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }
}
