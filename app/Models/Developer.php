<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Developer extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'manager_id',
    ];

    /**
     * Get the manager who manages this developer.
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(Manager::class);
    }

    /**
     * Get the projects this developer is assigned to.
     */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'developer_project')
                    ->withPivot('position', 'joined_at')
                    ->withTimestamps();
    }

    /**
     * Get the tasks this developer is assigned to.
     */
    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'developer_task')
                    ->withPivot('role', 'assigned_at')
                    ->withTimestamps();
    }
}
