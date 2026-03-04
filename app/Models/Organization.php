<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'uid',
        'website',
        'location',
        'description',
        'created_by',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function members()
    {
        return $this->belongsToMany(User::class, 'organization_user')
            ->withPivot('joined_at');
    }

    public function roles()
    {
        return $this->hasMany(Role::class);
    }

    public function nodes()
    {
        return $this->hasMany(Node::class);
    }
}
