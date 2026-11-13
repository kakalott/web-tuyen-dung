<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Interview extends Model
{
    use HasFactory;

    protected $table = 'interviews';
    // Ẩn status khỏi mass-assignment
    protected $fillable = ['application_id', 'scheduled_at', 'location_or_mode', 'notes', 'cancel_reason', 'created_by_user_id'];

    protected $casts = [
        'scheduled_at' => 'datetime',
    ];

    public function application() { return $this->belongsTo(Application::class); }
    public function createdBy() { return $this->belongsTo(User::class, 'created_by_user_id'); }
}
