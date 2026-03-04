<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportAnswer extends Model
{
    //
    protected $fillable = [
        'submission_id',
        'question_id',
        'answer_data',
    ];

    protected $casts = [
        'answer_data' => 'array',
    ];

    public function submission()
    {
        return $this->belongsTo(ReportSubmission::class, 'submission_id');
    }

    public function question()
    {
        return $this->belongsTo(ReportQuestion::class, 'question_id');
    }
}
