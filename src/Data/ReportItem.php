<?php

namespace Justbetter\StatamicStructuredData\Data;

use Illuminate\Support\Fluent;
use Justbetter\StatamicStructuredData\Enums\ReportIssueSeverity;
use Justbetter\StatamicStructuredData\Enums\ReportIssueType;
use Justbetter\StatamicStructuredData\Enums\ReportItemType;

/**
 * @extends Fluent<string, mixed>
 *
 * @property string $id
 * @property string $issue_type
 * @property string $severity
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
final class ReportItem extends Fluent
{
    public static function make($attributes = []): static
    {
        /** @var array<string, mixed> $data */
        $data = is_array($attributes) ? $attributes : [];

        if (! isset($data['severity']) && isset($data['issue_type']) && is_string($data['issue_type'])) {
            $type = ReportIssueType::tryFrom($data['issue_type']);
            $data['severity'] = $type?->severity()->value ?? ReportIssueSeverity::Error->value;
        }

        return new self($data);
    }

    public function severity(): ReportIssueSeverity
    {
        $value = $this['severity'] ?? null;

        if (is_string($value) && ($severity = ReportIssueSeverity::tryFrom($value))) {
            return $severity;
        }

        $issueType = $this['issue_type'] ?? null;
        if (is_string($issueType) && ($type = ReportIssueType::tryFrom($issueType))) {
            return $type->severity();
        }

        return ReportIssueSeverity::Error;
    }

    public function issueType(): ReportIssueType
    {
        $value = $this['issue_type'] ?? null;

        return ReportIssueType::from(is_string($value) ? $value : ReportIssueType::IncompleteField->value);
    }

    public function itemType(): ReportItemType
    {
        $value = $this['item_type'] ?? null;

        return ReportItemType::from(is_string($value) ? $value : '');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'issue_type' => $this->issue_type,
            'severity' => $this->severity()->value,
            'item_type' => $this->item_type,
            'item_id' => $this->item_id,
            'item_title' => $this->item_title,
            'item_edit_url' => $this->item_edit_url,
            'item_url' => $this->item_url,
            'template_id' => $this->template_id,
            'template_title' => $this->template_title,
            'schema_type' => $this->schema_type,
            'field_path' => $this->field_path,
            'scope_handle' => $this->scope_handle,
            'scope_type' => $this->scope_type,
        ];
    }
}
