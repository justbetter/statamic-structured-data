<?php

namespace Justbetter\StatamicStructuredData\Enums;

enum ReportIssueType: string
{
    case MissingAutomaticTemplate = 'missing_automatic_template';
    case NoTemplateAssigned = 'no_template_assigned';
    case IncompleteField = 'incomplete_field';

    public function severity(): ReportIssueSeverity
    {
        return match ($this) {
            self::NoTemplateAssigned => ReportIssueSeverity::Warning,
            self::MissingAutomaticTemplate,
            self::IncompleteField => ReportIssueSeverity::Error,
        };
    }

    public function isError(): bool
    {
        return $this->severity() === ReportIssueSeverity::Error;
    }
}
