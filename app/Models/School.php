<?php

namespace App\Models;

use App\Models\Concerns\RecordsAuditTrail;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class School extends Model
{
    use HasFactory, RecordsAuditTrail, SoftDeletes;

    protected string $auditModule = 'Schools';

    protected $fillable = [
        'name', 'short_name', 'slug', 'domain', 'motto', 'email', 'phone', 'website',
        'address', 'logo_path', 'favicon_path', 'primary_color', 'secondary_color',
        'timezone', 'student_number_prefix', 'is_active',
    ];

    /** Per-request memo for currencySymbol(). A new request builds a new model. */
    protected ?string $resolvedCurrency = null;

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    public function settings(): HasMany
    {
        return $this->hasMany(SchoolSetting::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function guardians(): HasMany
    {
        return $this->hasMany(Guardian::class);
    }

    public function admissions(): HasMany
    {
        return $this->hasMany(Admission::class);
    }

    /** Prefix used when generating student numbers, e.g. GFI-2026-00125. */
    public function numberPrefix(): string
    {
        if ($this->student_number_prefix) {
            return Str::upper($this->student_number_prefix);
        }

        $initials = Str::of($this->name)->explode(' ')
            ->filter(fn (string $word) => $word !== '')
            ->map(fn (string $word) => Str::upper(Str::substr($word, 0, 1)))
            ->implode('');

        return Str::substr($initials ?: 'SCH', 0, 5);
    }

    public function initials(): string
    {
        return Str::substr($this->numberPrefix(), 0, 2);
    }

    /**
     * The currency this school bills in.
     *
     * Memoised on the model instance, which lives exactly one request, so the
     * money formatter can call this in a loop without a query per row while a
     * change made in settings still takes effect on the very next page load.
     * Global scopes are skipped deliberately: this must answer for *this*
     * school even when a platform administrator is working above all of them.
     */
    public function currencySymbol(): string
    {
        if ($this->resolvedCurrency !== null) {
            return $this->resolvedCurrency;
        }

        $stored = SchoolSetting::withoutGlobalScopes()
            ->where('school_id', $this->id)
            ->where('key', 'finance_currency')
            ->value('value');

        // The column is cast to an array on the model, so a scalar setting
        // comes back as the scalar it was stored as.
        $symbol = is_array($stored) ? reset($stored) : $stored;

        return $this->resolvedCurrency = filled($symbol) ? (string) $symbol : Money::CURRENCY;
    }
}
