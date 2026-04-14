<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Task extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'goal',
        'description',
        'status',
        'project_id',
        'chef_de_projet_id',
    ];

    /**
     * Get the project this task belongs to.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the chef de projet who validates this task.
     */
    public function chefDeProjet(): BelongsTo
    {
        return $this->belongsTo(ChefDeProjet::class);
    }

    /**
     * Get the SLA associated with this task.
     */
    public function slaTask(): HasOne
    {
        return $this->hasOne(SlaTask::class);
    }

    /**
     * Get the developers assigned to this task.
     */
    public function developers(): BelongsToMany
    {
        return $this->belongsToMany(Developer::class, 'developer_task')
                    ->withPivot('role', 'assigned_at')
                    ->withTimestamps();
    }

    /**
     * Keep legacy "goal" and newer "description" fields aligned.
     */
    public function setDescriptionAttribute(?string $value): void
    {
        $this->attributes['description'] = $value;

        if (array_key_exists('goal', $this->attributes) || $value !== null) {
            $this->attributes['goal'] = $value;
        }
    }

    /**
     * Keep legacy "goal" and newer "description" fields aligned.
     */
    public function setGoalAttribute(?string $value): void
    {
        $this->attributes['goal'] = $value;
        $this->attributes['description'] = $value;
    }

    /**
     * Update the status of this task.
     */
    public function updateStatus(string $status): bool
    {
        $normalizedStatus = $status === 'completed' ? 'done' : $status;

        return $this->update(['status' => $normalizedStatus]);
    }
}
