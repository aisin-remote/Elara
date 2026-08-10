<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaskMoveOperation extends Model
{
    public $timestamps = false;

    protected $fillable = ['operation_id', 'project_id', 'task_id', 'created_at'];
}
