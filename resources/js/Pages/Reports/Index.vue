<template>
    <div class="max-w-5xl 3xl:max-w-6xl mx-auto" data-max-width-wrapper>
        <Head :title="__('Structured Data Reports')" />

        <Header :title="__('Structured Data Reports')" icon="charts">
            <Button
                variant="primary"
                :text="__('Generate report')"
                :disabled="generating"
                @click="generateReport"
            />
        </Header>

        <div class="space-y-6">
            <Description>
                {{ __('Site') }}:
                <Text as="span" variant="strong" :text="site" />
            </Description>

            <Card v-if="generating">
                <div class="py-4 text-center">
                    <Description :text="__('Generating report…')" />
                </div>
            </Card>

            <Card v-else-if="!reports.length">
                <div class="py-6 text-center">
                    <Description :text="__('No reports yet. Generate one to see coverage, completeness, and issues.')" />
                </div>
            </Card>

            <Card v-else inset class="overflow-hidden">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>{{ __('Created') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Coverage') }}</th>
                            <th>{{ __('Completeness') }}</th>
                            <th>{{ __('Errors') }}</th>
                            <th>{{ __('Warnings') }}</th>
                            <th />
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="report in reports" :key="report.id">
                            <td>
                                <Text :text="formatDate(report.created_at)" />
                            </td>
                            <td>
                                <Badge size="sm" :color="statusColor(report.status)" :text="report.status" />
                            </td>
                            <td>
                                <Text :text="formatPercent(report.coverage_percent)" />
                            </td>
                            <td>
                                <Text :text="formatPercent(report.completeness_percent)" />
                            </td>
                            <td>
                                <Text
                                    :variant="countVariant(report.error_count, 'error')"
                                    :text="report.error_count ?? 0"
                                />
                            </td>
                            <td>
                                <Text
                                    :variant="countVariant(report.warning_count, 'warning')"
                                    :text="report.warning_count ?? 0"
                                />
                            </td>
                            <td class="text-right">
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    :text="__('View')"
                                    :href="showUrl(report.id)"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </Card>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { Head, router } from '@statamic/cms/inertia';
import { Badge, Button, Card, Description, Header, Text } from '@statamic/cms/ui';

const { site, reports, generateUrl, showUrlTemplate } = defineProps({
    site: { type: String, required: true },
    reports: { type: Array, required: true },
    generateUrl: { type: String, required: true },
    showUrlTemplate: { type: String, required: true },
});

const generating = ref(false);

function showUrl(id) {
    return showUrlTemplate.replace('__REPORT_ID__', id);
}

function formatDate(value) {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString();
}

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

function statusColor(status) {
    if (status === 'completed') {
        return 'green';
    }

    if (status === 'failed') {
        return 'red';
    }

    return 'default';
}

function sleep(ms) {
    return new Promise(resolve => {
        setTimeout(resolve, ms);
    });
}

function reloadReports() {
    return new Promise(resolve => {
        router.reload({
            only: ['reports'],
            onFinish: resolve,
        });
    });
}

async function pollUntilReportSettled(knownIds) {
    const maxAttempts = 90;

    for (let attempt = 0; attempt < maxAttempts; attempt++) {
        await sleep(2000);
        await reloadReports();

        const newReports = reports.filter(report => !knownIds.has(report.id));
        const stillRunning = reports.some(report => report.status === 'running');

        if (newReports.length > 0 && !stillRunning) {
            return;
        }
    }
}

async function generateReport() {
    generating.value = true;
    const knownIds = new Set(reports.map(report => report.id));

    try {
        const csrfToken =
            Statamic.$config?.get?.('csrfToken') ||
            document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
            '';

        const response = await fetch(generateUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ _token: csrfToken }),
        });

        const payload = await response.json();

        if (!response.ok) {
            throw new Error(payload.message || 'Failed to generate report');
        }

        if (payload.queued) {
            Statamic.$toast.success(payload.message || __('Report generation has been queued.'));
            await pollUntilReportSettled(knownIds);

            return;
        }

        if (payload.showUrl) {
            router.visit(payload.showUrl);

            return;
        }

        await reloadReports();
    } catch (error) {
        Statamic.$toast.error(error.message || __('Failed to generate report'));
    } finally {
        generating.value = false;
    }
}
</script>
