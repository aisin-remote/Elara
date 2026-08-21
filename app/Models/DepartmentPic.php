<?php

namespace App\Models;

use App\Support\GeneratesPublicId;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepartmentPic extends Model
{
    use GeneratesPublicId, HasFactory;

    protected $fillable = [
        'workspace_id',
        'organization_department_id',
        'organization_department_code',
        'pic_id',
    ];

    protected $casts = [
        'organization_department_id' => 'integer',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function pic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_id');
    }
}
