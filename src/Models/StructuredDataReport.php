<?php

namespace Justbetter\StatamicStructuredData\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $site
 * @property string $status
 * @property string|null $triggered_by
 * @property string|null $actor
 * @property string|null $error
 * @property string|null $template_id
 * @property int $items_scanned
 * @property int $error_count
 * @property int $warning_count
 * @property int $missing_automatic_template_count
 * @property int $no_template_assigned_count
 * @property int $incomplete_field_count
 * @property int $coverage_expected
 * @property int $coverage_present
 * @property int $items_with_template
 * @property int $items_complete
 * @property float|string $coverage_percent
 * @property float|string $completeness_percent
 * @property float|string $clean_percent
 * @property array<int, array<string, mixed>>|null $scopes
 * @property Carbon|null $created_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $updated_at
 */
class StructuredDataReport extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'structured_data_reports';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'site',
        'status',
        'triggered_by',
        'actor',
        'error',
        'template_id',
        'items_scanned',
        'error_count',
        'warning_count',
        'missing_automatic_template_count',
        'no_template_assigned_count',
        'incomplete_field_count',
        'coverage_expected',
        'coverage_present',
        'items_with_template',
        'items_complete',
        'coverage_percent',
        'completeness_percent',
        'clean_percent',
        'scopes',
        'completed_at',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'items_scanned' => 'integer',
            'error_count' => 'integer',
            'warning_count' => 'integer',
            'missing_automatic_template_count' => 'integer',
            'no_template_assigned_count' => 'integer',
            'incomplete_field_count' => 'integer',
            'coverage_expected' => 'integer',
            'coverage_present' => 'integer',
            'items_with_template' => 'integer',
            'items_complete' => 'integer',
            'coverage_percent' => 'float',
            'completeness_percent' => 'float',
            'clean_percent' => 'float',
            'scopes' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<StructuredDataReportItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(StructuredDataReportItem::class, 'report_id');
    }
}
