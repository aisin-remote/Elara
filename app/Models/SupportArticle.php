<?php

namespace App\Models;

use App\Support\GeneratesPublicId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupportArticle extends Model
{
    use GeneratesPublicId, HasFactory;

    protected $fillable = ['category', 'slug', 'title', 'body', 'is_published', 'archived_at'];

    protected $casts = ['is_published' => 'boolean', 'archived_at' => 'datetime'];

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true)->whereNull('archived_at');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('archived_at');
    }
}
