<?php

namespace App\Models;

use App\Models\Reminder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReminderList extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'name', 'color', 'position', 'is_inbox'];

    protected $casts = [
        'is_inbox' => 'boolean',
        'position' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(Reminder::class, 'list_id');
    }
}
