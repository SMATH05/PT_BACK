<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Schema;

class Task extends Model
{
    use HasFactory;

    /**
     * Cache task table column checks for the current request lifecycle.
     *
     * @var array<string, bool>
     */
    protected static array $columnExistsCache = [];

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
        if ($this->taskColumnExists('description')) {
            $this->attributes['description'] = $value;
        }

        if ($this->taskColumnExists('goal')) {
            $this->attributes['goal'] = $value;
        }
    }

    /**
     * Keep legacy "goal" and newer "description" fields aligned.
     */
    public function setGoalAttribute(?string $value): void
    {
        if ($this->taskColumnExists('goal')) {
            $this->attributes['goal'] = $value;
        }

        if ($this->taskColumnExists('description')) {
            $this->attributes['description'] = $value;
        }
    }

    /**
     * Update the status of this task.
     */
    public function updateStatus(string $status): bool
    {
        $normalizedStatus = $status === 'completed' ? 'done' : $status;

        return $this->update(['status' => $normalizedStatus]);
    }

    private function taskColumnExists(string $column): bool
    {
        $cacheKey = "{$this->getTable()}.{$column}";

        if (! array_key_exists($cacheKey, self::$columnExistsCache)) {
            self::$columnExistsCache[$cacheKey] = Schema::hasColumn($this->getTable(), $column);
        }

        return self::$columnExistsCache[$cacheKey];
    }
}
