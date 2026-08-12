<?php

namespace Justbetter\StatamicStructuredData\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $report_id
 * @property string $issue_type
 * @property string|null $severity
 * @property string $item_type
 * @property string $item_id
 * @property string|null $item_title
 * @property string|null $item_edit_url
 * @property string|null $item_url
 * @property string|null $template_id
 * @property string|null $template_title
 * @property string|null $schema_type
 * @property string|null $field_path
 * @property string|null $scope_handle
 * @property string|null $scope_type
 */
class StructuredDataReportItem extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'structured_data_report_items';

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'report_id',
        'issue_type',
        'severity',
        'item_type',
        'item_id',
        'item_title',
        'item_edit_url',
        'item_url',
        'template_id',
        'template_title',
        'schema_type',
        'field_path',
        'scope_handle',
        'scope_type',
    ];

    /**
     * @return BelongsTo<StructuredDataReport, $this>
     */
    public function report(): BelongsTo
    {
        return $this->belongsTo(StructuredDataReport::class, 'report_id');
    }
}
