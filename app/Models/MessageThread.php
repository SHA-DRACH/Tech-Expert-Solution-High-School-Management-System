<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MessageThread extends Model
{
    use BelongsToSchool, HasFactory;

    protected $fillable = ['school_id', 'started_by', 'student_id', 'subject', 'status', 'last_message_at'];

    protected function casts(): array
    {
        return ['last_message_at' => 'datetime'];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->oldest();
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'message_thread_user')
            ->withPivot(['last_read_at'])
            ->withTimestamps();
    }

    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Threads this user takes part in, and no others. Membership is the only
     * thing that grants access to a conversation.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->whereHas('participants', fn (Builder $q) => $q->where('users.id', $user->id));
    }

    public function includes(User $user): bool
    {
        return $this->participants()->where('users.id', $user->id)->exists();
    }

    public function hasUnreadFor(User $user): bool
    {
        $participant = $this->participants->firstWhere('id', $user->id);

        if ($participant === null) {
            return false;
        }

        $lastRead = $participant->pivot->last_read_at;

        return $lastRead === null || ($this->last_message_at && $this->last_message_at->gt($lastRead));
    }
}
