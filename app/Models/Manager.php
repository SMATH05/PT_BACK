<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Manager extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
    ];

    /**
     * Get the chefs de projet appointed by this manager.
     */
    public function chefDeProjets(): HasMany
    {
        return $this->hasMany(ChefDeProjet::class);
    }

    /**
     * Get the developers managed by this manager.
     */
    public function developers(): HasMany
    {
        return $this->hasMany(Developer::class);
    }

    /**
     * Get the projects created by this manager.
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * Create a new project.
     */
    public function createProject(array $attributes): Project
    {
        return $this->projects()->create($attributes);
    }

    /**
     * Assign a developer to a project.
     */
    public function assignDeveloper(Developer $developer, Project $project, string $position): void
    {
        $developer->projects()->attach($project->id, [
            'position'  => $position,
            'joined_at' => now(),
        ]);
    }

    /**
     * Assign a chef de projet to a project.
     */
    public function assignChefDeProjet(ChefDeProjet $chefDeProjet, Project $project): void
    {
        $project->update(['chef_de_projet_id' => $chefDeProjet->id]);
    }
}
