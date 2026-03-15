<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class DeveloperProject extends Pivot
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'developer_project';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'developer_id',
        'project_id',
        'position',
        'joined_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'joined_at' => 'datetime',
    ];
}
