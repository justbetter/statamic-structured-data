<template>
    <div class="structured-data-preview mt-4" v-if="hasTemplates">
        <div class="flex justify-end items-center mb-4">
            <div class="flex gap-2">
                <button
                    class="btn"
                    @click="showPreview = !showPreview"
                >
                    {{ showPreview ? __('Hide Preview') : __('Show Preview') }}
                </button>
                <button
                    class="btn"
                    @click="isPrettyPrint = !isPrettyPrint"
                    v-if="showPreview"
                >
                    {{ isPrettyPrint ? __('Raw') : __('Pretty') }}
                </button>
            </div>
        </div>

        <div v-if="loading" class="text-center py-4">
            <loading-graphic />
        </div>

        <div v-else-if="error" class="text-red-500 py-4">
            {{ error }}
        </div>

        <div v-else-if="showPreview" class="space-y-4">
            <div v-for="template in selectedTemplates" :key="template.id" class="border rounded p-4">
                <div class="flex justify-between items-center mb-2">
                    <h4 class="font-bold">{{ template.title }}</h4>
                    <button
                        class="btn-close"
                        @click="template.isCollapsed = !template.isCollapsed"
                    >
                        {{ template.isCollapsed ? '+' : '-' }}
                    </button>
                </div>

                <div v-show="!template.isCollapsed">
                    <pre class="structured-data-preview-code p-4 rounded overflow-auto">{{ JSON.stringify(template.structuredData, null, isPrettyPrint ? 2 : 0) }}</pre>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    mixins: [Fieldtype],

    inject: {
        storeName: {
            default: 'base',
        },
    },

    data() {
        return {
            selectedTemplates: [],
            showPreview: true,
            isPrettyPrint: true,
            loading: false,
            error: null,
        }
    },

    computed: {
        publishValues() {
            return this.resolvePublishValues();
        },

        hasTemplates() {
            return this.templateIds && this.templateIds.length > 0;
        },

        templateIds() {
            return this.publishValues?.structured_data_templates || [];
        },

        currentEntryId() {
            return this.publishValues?.id;
        },
    },

    watch: {
        templateIds: {
            immediate: true,
            handler(newValue) {
                if (!newValue || !newValue.length) {
                    this.selectedTemplates = [];
                    return;
                }
                this.fetchTemplateData(newValue);
            }
        },

        publishValues: {
            deep: true,
            handler() {
                if (!this.templateIds || !this.templateIds.length) {
                    return;
                }
                this.fetchTemplateData(this.templateIds);
            },
        },
    },

    methods: {
        resolvePublishValues() {
            const preferredStore = this.storeName || 'base';
            const publish = this.$store?.state?.publish;

            if (publish) {
                const preferredValues = publish[preferredStore]?.values;

                if (this.isPublishValues(preferredValues)) {
                    return preferredValues;
                }

                for (const storeKey of Object.keys(publish)) {
                    const values = publish[storeKey]?.values;

                    if (this.isPublishValues(values)) {
                        return values;
                    }
                }
            }

            return {};
        },

        isPublishValues(values) {
            return values && typeof values === 'object' && !Array.isArray(values);
        },

        async fetchTemplateData(templateIds) {
            if (!this.currentEntryId) {
                this.error = 'Save the entry first to preview structured data.';
                return;
            }

            this.loading = true;
            this.error = null;

            try {
                const payload = {
                    ids: templateIds,
                    entry_id: this.currentEntryId,
                };

                const values = this.publishValues;

                if (this.isPublishValues(values)) {
                    payload.values = values;
                }

                const response = await this.$axios.post(`/cp/justbetter/structured-data`, payload);

                this.selectedTemplates = response.data.map(template => ({
                    ...template,
                    isCollapsed: false
                }));
            } catch (error) {
                console.error('Error fetching template data:', error);
                this.error = 'Failed to load template data. Please try again.';
            } finally {
                this.loading = false;
            }
        },
    }
}
</script>

<style>
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
