<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('structured_data_reports', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('site');
            $table->string('status');
            $table->string('triggered_by')->nullable();
            $table->string('actor')->nullable();
            $table->text('error')->nullable();
            $table->string('template_id')->nullable();
            $table->unsignedInteger('items_scanned')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->unsignedInteger('warning_count')->default(0);
            $table->unsignedInteger('missing_automatic_template_count')->default(0);
            $table->unsignedInteger('no_template_assigned_count')->default(0);
            $table->unsignedInteger('incomplete_field_count')->default(0);
            $table->unsignedInteger('coverage_expected')->default(0);
            $table->unsignedInteger('coverage_present')->default(0);
            $table->unsignedInteger('items_with_template')->default(0);
            $table->unsignedInteger('items_complete')->default(0);
            $table->decimal('coverage_percent', 5, 1)->default(100);
            $table->decimal('completeness_percent', 5, 1)->default(100);
            $table->decimal('clean_percent', 5, 1)->default(100);
            $table->json('scopes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['site', 'created_at']);
        });

        Schema::create('structured_data_report_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('report_id');
            $table->string('issue_type');
            $table->string('severity')->default('error');
            $table->string('item_type');
            $table->string('item_id');
            $table->string('item_title')->nullable();
            $table->string('item_edit_url')->nullable();
            $table->string('item_url')->nullable();
            $table->string('template_id')->nullable();
            $table->string('template_title')->nullable();
            $table->string('schema_type')->nullable();
            $table->string('field_path')->nullable();
            $table->string('scope_handle')->nullable();
            $table->string('scope_type')->nullable();

            $table->foreign('report_id')
                ->references('id')
                ->on('structured_data_reports')
                ->cascadeOnDelete();

            $table->index(['report_id', 'issue_type']);
            $table->index(['report_id', 'severity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('structured_data_report_items');
        Schema::dropIfExists('structured_data_reports');
    }
};
