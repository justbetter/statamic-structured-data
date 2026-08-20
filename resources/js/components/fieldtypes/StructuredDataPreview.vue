<template>
    <div
        v-if="hasTemplates"
        class="structured-data-preview mt-4"
    >
        <div class="flex justify-end items-center mb-4">
            <div class="flex gap-2">
                <Button @click="togglePreview">
                    {{ showPreview ? __('Hide Preview') : __('Show Preview') }}
                </Button>
                <Button
                    v-if="showPreview"
                    @click="togglePrettyPrint"
                >
                    {{ isPrettyPrint ? __('Raw') : __('Pretty') }}
                </Button>
                <Button
                    v-if="showPreview && hasPreviewData"
                    :text="schemaCopied ? __('Copied!') : __('Schema Validator')"
                    :title="__('Copies JSON-LD and opens the Schema Markup Validator. Paste the code into the Code tab.')"
                    :disabled="openingSchemaValidator"
                    @click="openSchemaValidator"
                />
            </div>
        </div>

        <div
            v-if="loading"
            class="text-center py-4 text-gray-500"
        >
            {{ __('Loading structured data preview...') }}
        </div>

        <div
            v-else-if="error"
            class="text-red-500 py-4"
        >
            {{ error }}
        </div>

        <div
            v-else-if="showPreview"
            class="space-y-4"
        >
            <div
                v-for="template in selectedTemplates"
                :key="template.id"
                class="border rounded p-4"
            >
                <div class="flex justify-between items-center mb-2">
                    <h4 class="font-bold">
                        {{ template.title }}
                    </h4>
                    <Button @click="toggleTemplateCollapse(template)">
                        {{ template.isCollapsed ? '+' : '-' }}
                    </Button>
                </div>

                <div v-show="!template.isCollapsed">
                    <pre class="structured-data-preview-code p-4 rounded overflow-auto">
                        {{ JSON.stringify(template.structuredData, null, isPrettyPrint ? 2 : 0) }}
                    </pre>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch, getCurrentInstance } from 'vue';
import { Fieldtype } from '@statamic/cms';
import { Button, injectPublishContext } from '@statamic/cms/ui';

const fieldtypeProps = defineProps(Fieldtype.props);
const { meta } = fieldtypeProps;

const emit = defineEmits(Fieldtype.emits);
const { expose } = Fieldtype.use(emit, fieldtypeProps);

defineExpose(expose);

const { values, site } = injectPublishContext();
const { proxy } = getCurrentInstance();

const axiosInstance = proxy?.$axios ?? window?.axios ?? window?.Statamic?.$axios;

const selectedTemplates = ref([]);
const showPreview = ref(true);
const isPrettyPrint = ref(true);
const loading = ref(false);
const error = ref(null);
const openingSchemaValidator = ref(false);
const schemaCopied = ref(false);
let schemaCopiedTimeout = null;

const templateIds = computed(() => {
    const publishValues = values.value || {};

    return publishValues.structured_data_templates || [];
});

const currentEntryId = computed(() => {
    const publishValues = values.value || {};

    return publishValues.id;
});

const currentSite = computed(() => site?.value ?? null);

const hasTemplates = computed(() => {
    return templateIds.value && templateIds.value.length > 0;
});

const hasPreviewData = computed(() => {
    return !loading.value && !error.value && selectedTemplates.value.length > 0;
});

const schemaValidatorUrl = computed(() => {
    return meta?.schema_validator_url || 'https://validator.schema.org/';
});

const togglePreview = () => {
    showPreview.value = !showPreview.value;
};

const togglePrettyPrint = () => {
    isPrettyPrint.value = !isPrettyPrint.value;
};

const toggleTemplateCollapse = template => {
    template.isCollapsed = !template.isCollapsed;
};

const buildJsonLdScripts = () => {
    const scripts = [];

    for (const template of selectedTemplates.value) {
        const schemas = Array.isArray(template.structuredData)
            ? template.structuredData
            : [template.structuredData];

        for (const schema of schemas) {
            if (!schema || typeof schema !== 'object') {
                continue;
            }

            scripts.push(
                `<script type="application/ld+json">${JSON.stringify(schema)}<\/script>`,
            );
        }
    }

    return scripts.join('\n\n');
};

const flashSchemaCopied = () => {
    schemaCopied.value = true;

    if (schemaCopiedTimeout) {
        clearTimeout(schemaCopiedTimeout);
    }

    schemaCopiedTimeout = setTimeout(() => {
        schemaCopied.value = false;
        schemaCopiedTimeout = null;
    }, 2000);
};

const openSchemaValidator = async () => {
    const text = buildJsonLdScripts();

    if (!text) {
        Statamic.$toast.error(__('Failed to copy JSON-LD'));

        return;
    }

    openingSchemaValidator.value = true;

    try {
        await navigator.clipboard.writeText(text);
        flashSchemaCopied();
        Statamic.$toast.success(
            __('JSON-LD copied to clipboard. Paste it into the Code tab on the Schema Markup Validator.'),
        );
        window.open(schemaValidatorUrl.value, '_blank', 'noopener,noreferrer');
    } catch (copyError) {
        // eslint-disable-next-line no-console
        console.error('Failed to copy JSON-LD for Schema Markup Validator:', copyError);
        Statamic.$toast.error(__('Failed to copy JSON-LD'));
    } finally {
        openingSchemaValidator.value = false;
    }
};

const fetchTemplateData = async templateIdsToFetch => {
    if (!axiosInstance) {
        error.value = 'Failed to load template data. Please try again.';

        // eslint-disable-next-line no-console
        console.error('Axios instance is not available for StructuredDataPreview.');

        return;
    }

    loading.value = true;
    error.value = null;

    try {
        const response = await axiosInstance.get('/cp/justbetter/structured-data', {
            params: {
                ids: templateIdsToFetch,
                entry_id: currentEntryId.value,
                site: currentSite.value,
            },
        });

        selectedTemplates.value = response.data.map(template => ({
            ...template,
            isCollapsed: false,
        }));
    } catch (fetchError) {
        // eslint-disable-next-line no-console
        console.error('Error fetching template data:', fetchError);
        error.value = 'Failed to load template data. Please try again.';
    } finally {
        loading.value = false;
    }
};

watch(
    templateIds,
    newTemplateIds => {
        if (!newTemplateIds || !newTemplateIds.length) {
            selectedTemplates.value = [];

            return;
        }

        fetchTemplateData(newTemplateIds);
    },
    { immediate: true },
);
</script>

<style>
@reference "../../../css/statamic-structured-data.css";

.structured-data-preview pre {
    max-height: 400px;
}

.btn {
    @apply bg-gray-200 px-3 py-1 rounded hover:bg-gray-300;
}

.btn-close {
    @apply px-2 py-1 text-gray-500 hover:text-gray-700;
}
</style>
