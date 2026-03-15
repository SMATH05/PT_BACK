<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChefDeProjet extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'chef_de_projets';

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
     * Get the manager who appointed this chef de projet.
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(Manager::class);
    }

    /**
     * Get the projects supervised by this chef de projet.
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * Get the tasks validated by this chef de projet.
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Supervise a project (assign self to project).
     */
    public function superviseProject(Project $project): void
    {
        $project->update(['chef_de_projet_id' => $this->id]);
    }

    /**
     * Validate a deliverable (mark task as validated).
     */
    public function validateDeliverable(Task $task): void
    {
        $task->update([
            'status'            => 'validated',
            'chef_de_projet_id' => $this->id,
        ]);
    }
}
