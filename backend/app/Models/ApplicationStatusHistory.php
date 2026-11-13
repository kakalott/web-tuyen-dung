<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApplicationStatusHistory extends Model
{
    use HasFactory;

    protected $table = 'application_status_histories';
    public $timestamps = false; // Bảng lịch sử không dùng created_at, updated_at
    protected $fillable = ['application_id', 'from_status', 'to_status', 'changed_by_user_id', 'note', 'changed_at'];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    public function application() { return $this->belongsTo(Application::class); }
    public function changedBy() { return $this->belongsTo(User::class, 'changed_by_user_id'); }
}
