<?php

namespace App\Models;

use App\Domain\Audit\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalStep extends Model
{
    use Auditable, HasFactory;

    protected $table = 'approval_steps';

    protected $fillable = [
        'approval_workflow_id',
        'sequence',
        'name',
        'permission_code',
        'department_scoped',
        'is_required',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'department_scoped' => 'boolean',
            'is_required' => 'boolean',
        ];
    }

    public function approvalWorkflow(): BelongsTo
    {
        return $this->belongsTo(ApprovalWorkflow::class, 'approval_workflow_id');
    }
}
