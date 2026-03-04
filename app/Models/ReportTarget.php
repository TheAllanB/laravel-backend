<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportTarget extends Model
{
    //
    protected $fillable = [
        'report_id',
        'target_type',
        'target_id',
    ];

    public function report()
    {
        return $this->belongsTo(Report::class);
    }
}
