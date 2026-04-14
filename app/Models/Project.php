<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;
use App\Models\ProjectFile;

class Project extends Model
{
    use HasFactory;

    /**
     * Computed attributes exposed in API responses.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'absolute_folder_path',
        'vscode_url',
        'workspace_exists',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'client',
        'description',
        'start_date',
        'end_date',
        'deadline',
        'status',
        'manager_id',
        'chef_de_projet_id',
        'folder_path',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'deadline' => 'date',
    ];

    /**
     * Get the manager who created this project.
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(Manager::class);
    }

    /**
     * Get the chef de projet who supervises this project.
     */
    public function chefDeProjet(): BelongsTo
    {
        return $this->belongsTo(ChefDeProjet::class);
    }

    /**
     * Get the tasks contained in this project.
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Get the SLA associated with this project.
     */
    public function slaProject(): HasOne
    {
        return $this->hasOne(SlaProject::class);
    }

    /**
     * Get the developers assigned to this project.
     */
    public function developers(): BelongsToMany
    {
        return $this->belongsToMany(Developer::class, 'developer_project')
                    ->withPivot('position', 'joined_at')
                    ->withTimestamps();
    }

    /**
     * Get the files uploaded to this project.
     */
    public function files(): HasMany
    {
        return $this->hasMany(ProjectFile::class);
    }

    public function getAbsoluteFolderPathAttribute(): ?string
    {
        if (! $this->folder_path) {
            return null;
        }

        return storage_path('app/' . $this->folder_path);
    }

    public function getVscodeUrlAttribute(): ?string
    {
        if (! $this->absolute_folder_path) {
            return null;
        }

        return 'vscode://file/' . str_replace('\\', '/', $this->absolute_folder_path);
    }

    public function getWorkspaceExistsAttribute(): bool
    {
        if (! $this->folder_path) {
            return false;
        }

        return Storage::disk(config('filesystems.default', 'local'))
            ->directoryExists($this->folder_path);
    }

    /**
     * Get the progress of the project (percentage of completed tasks).
     */
    public function getProgress(): float
    {
        $totalTasks = $this->tasks()->count();

        if ($totalTasks === 0) {
            return 0.0;
        }

        $completedTasks = $this->tasks()->whereIn('status', ['done', 'completed'])->count();

        return round(($completedTasks / $totalTasks) * 100, 2);
    }
}
