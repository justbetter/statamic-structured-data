<template>
    <div class="max-w-5xl 3xl:max-w-6xl mx-auto" data-max-width-wrapper>
        <Head :title="__('Structured Data Report')" />

        <Header :title="__('Structured Data Report')" icon="charts">
            <Button
                :text="__('Back to reports')"
                :href="indexUrl"
                as="a"
            />
        </Header>

        <div class="space-y-6">
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-4">
                <Card class="lg:col-span-1 flex flex-col items-center justify-center gap-3 py-6">
                    <div class="relative" style="width: 144px; height: 144px;">
                        <div class="absolute inset-0 z-0 flex items-center justify-center pointer-events-none">
                            <div class="text-center">
                                <Text as="div" variant="strong" size="lg" :text="formatPercent(report.clean_percent)" />
                                <Badge size="sm" :color="gradeColor" :text="gradeLabel" />
                            </div>
                        </div>
                        <div class="relative z-10 h-full w-full">
                            <Doughnut :data="chartData" :options="chartOptions" />
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <Badge color="green" size="sm" :text="__('OK')" />
                        <Badge color="red" size="sm" :text="__('Errors')" />
                    </div>
                </Card>

                <Card>
                    <Description :text="__('Coverage')" />
                    <Heading size="2xl" :text="formatPercent(report.coverage_percent)" />
                    <Description :text="__('Automatic template assignments')" />
                </Card>

                <Card>
                    <Description :text="__('Completeness')" />
                    <Heading size="2xl" :text="formatPercent(report.completeness_percent)" />
                    <Description :text="__('Items with templates and no empty fields')" />
                </Card>

                <Card>
                    <Description :text="__('Issues')" />
                    <div class="flex items-center gap-2">
                        <Badge
                            size="lg"
                            :color="(report.error_count ?? 0) > 0 ? 'red' : 'green'"
                            :text="report.error_count ?? 0"
                        />
                        <Text as="span" variant="subtle" text="/" />
                        <Badge
                            size="lg"
                            :color="(report.warning_count ?? 0) > 0 ? 'amber' : 'green'"
                            :text="report.warning_count ?? 0"
                        />
                    </div>
                    <Description>
                        {{ __('Errors') }} / {{ __('Warnings') }}
                    </Description>
                </Card>
            </div>

            <div v-if="scopes.length" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <button
                    v-for="scope in scopes"
                    :key="scope.key"
                    type="button"
                    class="text-start w-full"
                    @click="selectScope(scope.key)"
                >
                    <Card :variant="activeScope === scope.key ? 'default' : 'flat'">
                        <div class="flex items-center justify-between gap-2">
                            <Heading :text="scopeLabel(scope)" />
                            <Badge
                                v-if="activeScope === scope.key"
                                color="blue"
                                size="sm"
                                :text="__('Filtered')"
                            />
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <Description>
                                {{ __('Coverage') }}:
                                <Text as="span" variant="strong" :text="formatPercent(scope.coverage_percent)" />
                            </Description>
                            <Description>
                                {{ __('Completeness') }}:
                                <Text as="span" variant="strong" :text="formatPercent(scope.completeness_percent)" />
                            </Description>
                            <Description>
                                {{ __('Errors') }}:
                                <Text
                                    as="span"
                                    :variant="countVariant(scope.error_count, 'error')"
                                    :text="scope.error_count"
                                />
                            </Description>
                            <Description>
                                {{ __('Warnings') }}:
                                <Text
                                    as="span"
                                    :variant="countVariant(scope.warning_count, 'warning')"
                                    :text="scope.warning_count"
                                />
                            </Description>
                        </div>
                    </Card>
                </button>
            </div>

            <Card>
                <div class="flex flex-wrap items-center gap-2 mb-4">
                    <Tabs :model-value="activeSeverity" @update:model-value="selectSeverity">
                        <TabList>
                            <TabTrigger name="error">
                                {{ __('Errors') }}
                                <Badge
                                    class="ms-1"
                                    size="sm"
                                    :color="(report.error_count ?? 0) > 0 ? 'red' : 'green'"
                                    :text="report.error_count ?? 0"
                                />
                            </TabTrigger>
                            <TabTrigger name="warning">
                                {{ __('Warnings') }}
                                <Badge
                                    class="ms-1"
                                    size="sm"
                                    :color="(report.warning_count ?? 0) > 0 ? 'amber' : 'green'"
                                    :text="report.warning_count ?? 0"
                                />
                            </TabTrigger>
                        </TabList>
                    </Tabs>

                    <Button
                        v-if="activeScope"
                        class="ms-auto"
                        variant="ghost"
                        size="sm"
                        :text="__('Clear scope filter')"
                        @click="selectScope('')"
                    />
                </div>

                <div v-if="!items.length" class="py-6 text-center">
                    <Description :text="__('No issues match the current filters.')" />
                </div>

                <div v-else class="overflow-x-auto -mx-4 sm:mx-0">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>{{ __('Issue') }}</th>
                                <th>{{ __('Item') }}</th>
                                <th>{{ __('Template') }}</th>
                                <th>{{ __('Schema') }}</th>
                                <th>{{ __('Field') }}</th>
                                <th>{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in items" :key="item.id">
                                <td>
                                    <Badge
                                        size="sm"
                                        :color="item.severity === 'warning' ? 'amber' : 'red'"
                                        :text="issueLabel(item.issue_type)"
                                    />
                                </td>
                                <td>
                                    <Button
                                        v-if="item.item_edit_url"
                                        variant="ghost"
                                        size="sm"
                                        inset
                                        :text="item.item_title || item.item_id"
                                        :href="item.item_edit_url"
                                        as="a"
                                    />
                                    <Text v-else :text="item.item_title || item.item_id" />
                                </td>
                                <td>
                                    <Text :text="item.template_title || item.template_id || '—'" />
                                </td>
                                <td>
                                    <Text :text="item.schema_type || '—'" />
                                </td>
                                <td>
                                    <Text :text="item.field_path || '—'" />
                                </td>
                                <td class="space-x-2 whitespace-nowrap">
                                    <Button
                                        size="sm"
                                        :text="schemaCopiedItemId === item.id ? __('Copied!') : __('Schema Validator')"
                                        :title="__('Copies JSON-LD and opens the Schema Markup Validator. Paste the code into the Code tab.')"
                                        @click="openSchemaValidator(item)"
                                    />
                                    <Button
                                        size="sm"
                                        :text="jsonLdCopiedItemId === item.id ? __('Copied!') : __('Copy JSON-LD')"
                                        @click="copyJsonLd(item)"
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </Card>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { ArcElement, Chart as ChartJS, Tooltip } from 'chart.js';
import { Doughnut } from 'vue-chartjs';
import { Head, router } from '@statamic/cms/inertia';
import {
    Badge,
    Button,
    Card,
    Description,
    Header,
    Heading,
    TabList,
    Tabs,
    TabTrigger,
    Text,
} from '@statamic/cms/ui';

ChartJS.register(ArcElement, Tooltip);

const {
    report,
    items,
    filters,
    schemaValidatorUrl,
    indexUrl,
    jsonLdUrlTemplate,
} = defineProps({
    report: { type: Object, required: true },
    items: { type: Array, required: true },
    filters: { type: Object, required: true },
    schemaValidatorUrl: { type: String, required: true },
    indexUrl: { type: String, required: true },
    jsonLdUrlTemplate: { type: String, required: true },
});

const activeSeverity = ref(filters.severity || 'error');
const activeScope = ref(filters.scope || '');
const schemaCopiedItemId = ref(null);
const jsonLdCopiedItemId = ref(null);
const schemaCopiedTimeoutRef = { current: null };
const jsonLdCopiedTimeoutRef = { current: null };

const scopes = computed(() => report.scopes || []);

const cleanPercent = computed(() => {
    return Math.min(100, Math.max(0, Number(report.clean_percent ?? 100)));
});

const grade = computed(() => {
    const percent = cleanPercent.value;

    if (percent >= 80) {
        return 'good';
    }

    if (percent >= 50) {
        return 'fair';
    }

    return 'bad';
});

const gradeLabel = computed(() => {
    if (grade.value === 'good') {
        return __('Good');
    }

    if (grade.value === 'fair') {
        return __('Fair');
    }

    return __('Bad');
});

const gradeColor = computed(() => {
    if (grade.value === 'good') {
        return 'green';
    }

    if (grade.value === 'fair') {
        return 'amber';
    }

    return 'red';
});

const chartData = computed(() => {
    const clean = cleanPercent.value;
    const errors = Math.max(0, Number((100 - clean).toFixed(1)));

    return {
        labels: [__('OK'), __('Errors')],
        datasets: [
            {
                data: errors > 0 ? [clean, errors] : [100],
                backgroundColor: errors > 0 ? ['#22c55e', '#ef4444'] : ['#22c55e'],
                borderWidth: 0,
                hoverOffset: 2,
            },
        ],
    };
});

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    cutout: '72%',
    plugins: {
        legend: {
            display: false,
        },
        tooltip: {
            callbacks: {
                label(context) {
                    const value = context.parsed;

                    return `${context.label}: ${Number(value).toFixed(1)}%`;
                },
            },
        },
    },
};

function formatPercent(value) {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    return `${Number(value).toFixed(1)}%`;
}

function countVariant(count, type) {
    if (Number(count) <= 0) {
        return 'success';
    }

    return type === 'warning' ? 'warning' : 'danger';
}

function scopeLabel(scope) {
    return `${scope.scope_type}: ${scope.scope_handle}`;
}

function issueLabel(type) {
    const labels = {
        missing_automatic_template: __('Missing automatic template'),
        no_template_assigned: __('No template assigned'),
        incomplete_field: __('Incomplete field'),
    };

    return labels[type] || type;
}

function applyFilters() {
    router.get(
        window.location.pathname,
        {
            severity: activeSeverity.value || undefined,
            scope: activeScope.value || undefined,
        },
        { preserveState: true, replace: true },
    );
}

function selectSeverity(severity) {
    activeSeverity.value = severity;
    applyFilters();
}

function selectScope(scope) {
    activeScope.value = activeScope.value === scope ? '' : scope;
    applyFilters();
}

function flashCopied(itemId, targetRef, timeoutRef) {
    targetRef.value = itemId;

    if (timeoutRef.current) {
        clearTimeout(timeoutRef.current);
    }

    timeoutRef.current = setTimeout(() => {
        if (targetRef.value === itemId) {
            targetRef.value = null;
        }

        timeoutRef.current = null;
    }, 2000);
}

async function fetchJsonLdText(item) {
    const url = jsonLdUrlTemplate.replace('__ITEM_ID__', item.id);
    const response = await fetch(url, {
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    });
    const payload = await response.json();

    if (!response.ok) {
        throw new Error(payload.message || 'Unable to load JSON-LD');
    }

    return (payload.scripts || []).join('\n\n');
}

async function openSchemaValidator(item) {
    try {
        const text = await fetchJsonLdText(item);
        const scripts = (text || '')
            .split('\n\n')
            .map(chunk => chunk.trim())
            .filter(Boolean)
            .map(chunk => {
                if (chunk.startsWith('<script')) {
                    return chunk;
                }

                return `<script type="application/ld+json">${chunk}<\/script>`;
            })
            .join('\n\n');

        await navigator.clipboard.writeText(scripts);
        flashCopied(item.id, schemaCopiedItemId, schemaCopiedTimeoutRef);
        Statamic.$toast.success(
            __('JSON-LD copied to clipboard. Paste it into the Code tab on the Schema Markup Validator.'),
        );
        window.open(schemaValidatorUrl, '_blank', 'noopener,noreferrer');
    } catch (error) {
        Statamic.$toast.error(error.message || __('Failed to copy JSON-LD'));
    }
}

async function copyJsonLd(item) {
    try {
        const text = await fetchJsonLdText(item);
        await navigator.clipboard.writeText(text || '');
        flashCopied(item.id, jsonLdCopiedItemId, jsonLdCopiedTimeoutRef);
        Statamic.$toast.success(__('JSON-LD copied to clipboard'));
    } catch (error) {
        Statamic.$toast.error(error.message || __('Failed to copy JSON-LD'));
    }
}
</script>
