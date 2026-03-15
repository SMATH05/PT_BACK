<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SlaProject extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'sla_projects';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'max_response_time',
        'max_resolution_time',
        'priority',
        'project_id',
    ];

    /**
     * Get the project this SLA belongs to.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Check if the project complies with this SLA.
     *
     * @param int $responseTime  Actual response time in minutes.
     * @param int $resolutionTime Actual resolution time in minutes.
     * @return array{compliant: bool, response_ok: bool, resolution_ok: bool}
     */
    public function checkCompliance(int $responseTime, int $resolutionTime): array
    {
        $responseOk   = $responseTime <= $this->max_response_time;
        $resolutionOk = $resolutionTime <= $this->max_resolution_time;

        return [
            'compliant'     => $responseOk && $resolutionOk,
            'response_ok'   => $responseOk,
            'resolution_ok' => $resolutionOk,
        ];
    }
}
