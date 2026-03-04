<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    //
    protected $fillable = [
        'organization_id',
        'creator_id',
        'title',
        'description',
        'deadline',
    ];

    protected $casts = [
        'deadline' => 'datetime',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function questions()
    {
        return $this->hasMany(ReportQuestion::class);
    }

    public function targets()
    {
        return $this->hasMany(ReportTarget::class);
    }

    public function submissions()
    {
        return $this->hasMany(ReportSubmission::class);
    }
}
