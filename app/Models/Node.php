<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Node extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'parent_id',
        'name',
        'type',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function parent()
    {
        return $this->belongsTo(Node::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Node::class, 'parent_id');
    }

    // Recursively fetch all children
    public function descendents()
    {
        return $this->children()->with('descendents');
    }

    // Recursively fetch all parents
    public function ancestors()
    {
        return $this->parent()->with('ancestors');
    }
}
