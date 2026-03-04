<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportQuestion extends Model
{
    //
    protected $fillable = [
        'report_id',
        'type',
        'title',
        'is_required',
        'options',
        'order_index',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'options' => 'array',
    ];

    public function report()
    {
        return $this->belongsTo(Report::class);
    }
}
