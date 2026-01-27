<template>
    <div class="replicator-mapper space-y-3">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div>
                <label class="text-gray-600 mb-1 block">{{ __('Replicator Field') }}</label>
                <v-select
                    v-model="localConfig.replicator_field"
                    :options="replicatorFieldOptions"
                    @input="(val) => { localConfig.replicator_field = val ? val.value : ''; localConfig.set = ''; }"
                    :placeholder="replicatorFieldOptions.length > 0 ? __('Select replicator field') : __('No replicator fields available')"
                    :disabled="replicatorFieldOptions.length === 0"
                />
                <div v-if="replicatorFieldOptions.length === 0" class="text-xs text-yellow-600 mt-1 bg-yellow-50 p-2 rounded">
                    <strong>{{ __('No replicator fields found.') }}</strong><br>
                    {{ __('Make sure your Structured Data Template has "Use for collection" or "Use for taxonomy" set, and that collection/taxonomy has replicator fields in its blueprint.') }}
                </div>
            </div>
            <div>
                <label class="text-gray-600 mb-1 block">{{ __('Limit to Set (optional)') }}</label>
                <v-select
                    v-model="localConfig.set"
                    :options="setOptions"
                    @input="(val) => { localConfig.set = val ? val.value : ''; }"
                    :placeholder="__('All sets')"
                    :clearable="true"
                />
            </div>
        </div>

        <div class="border rounded p-3">
            <div class="flex items-center justify-between mb-2">
                <h4 class="font-semibold text-gray-700">{{ __('Field Mappings') }}</h4>
                <button class="btn-primary text-sm" @click="addMapping">{{ __('Add Mapping') }}</button>
            </div>

            <div v-if="!localConfig.mappings.length" class="text-sm text-gray-500">
                {{ __('No mappings yet. Add one to map replicator fields into your JSON-LD object.') }}
            </div>

            <div
                v-for="(mapping, index) in localConfig.mappings"
                :key="index"
                class="border rounded mb-3 p-3"
            >
                <div class="flex items-start gap-2">
                    <div class="flex-1">
                        <label class="text-gray-600 mb-1 block">{{ __('JSON-LD Key') }}</label>
                        <input
                            type="text"
                            v-model="mapping.key"
                            class="input-text w-full"
                            placeholder="e.g. name"
                            @input="sanitizeKey(mapping)"
                        />
                    </div>
                    <div class="w-40">
                        <label class="text-gray-600 mb-1 block">{{ __('Source') }}</label>
                        <v-select
                            v-model="mapping.mode"
                            :options="modeOptions"
                            @input="(val) => { mapping.mode = val.value; }"
                        />
                    </div>
                    <button class="btn-danger mt-6" @click="removeMapping(index)">{{ __('Remove') }}</button>
                </div>

                <div class="mt-2">
                    <template v-if="mapping.mode === 'static'">
                        <label class="text-gray-600 mb-1 block">{{ __('Static Value') }}</label>
                        <input
                            type="text"
                            v-model="mapping.static"
                            class="input-text w-full"
                            :placeholder="__('e.g. PropertyValue')"
                        />
                    </template>

                    <template v-else-if="mapping.mode === 'field'">
                        <label class="text-gray-600 mb-1 block">{{ __('Replicator Field') }}</label>
                        <v-select
                            v-model="mapping.field"
                            :options="getFieldOptionsForMapping(mapping)"
                            @input="(val) => { mapping.field = val ? val.value : ''; }"
                            :placeholder="__('Select field')"
                        />
                    </template>

                    <template v-else-if="mapping.mode === 'nested_replicator'">
                        <div class="p-3 border rounded">
                            <replicator-field-mapper
                                v-model="mapping.nested"
                                :replicator-fields="replicatorFields"
                            />
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'ReplicatorFieldMapper',
    props: {
        value: {
            type: Object,
            default: () => ({
                replicator_field: '',
                set: '',
                mappings: []
            })
        },
        replicatorFields: {
            type: Array,
            default: () => []
        }
    },
    data() {
        return {
            localConfig: this.normalizeConfig(this.value),
            modeOptions: [
                { value: 'field', label: 'From Replicator Field' },
                { value: 'static', label: 'Static Value' },
                { value: 'nested_replicator', label: 'Nested Replicator' },
            ],
        }
    },
    computed: {
        availableReplicatorFields() {
            if (!this.replicatorFields || !Array.isArray(this.replicatorFields)) {
                return [];
            }
            return this.replicatorFields;
        },
        replicatorFieldOptions() {
            if (!this.availableReplicatorFields || this.availableReplicatorFields.length === 0) {
                return [];
            }
            return this.availableReplicatorFields.map(field => ({
                value: field.handle,
                label: field.display || field.handle
            }));
        },
        selectedReplicatorField() {
            if (!this.localConfig.replicator_field) {
                return null;
            }
            return this.availableReplicatorFields.find(
                field => field.handle === this.localConfig.replicator_field
            );
        },
        setOptions() {
            if (!this.selectedReplicatorField) {
                return [];
            }
            return this.selectedReplicatorField.sets.map(set => ({
                value: set.value,
                label: set.label
            }));
        }
    },
    watch: {
        localConfig: {
            deep: true,
            handler(val) {
                const newVal = JSON.stringify(val);
                const oldVal = JSON.stringify(this.value);

                if (newVal !== oldVal) {
                    this.$emit('input', JSON.parse(newVal));
                }
            }
        },
        value: {
            deep: true,
            handler(val) {
                const newVal = JSON.stringify(this.normalizeConfig(val));
                const oldVal = JSON.stringify(this.localConfig);

                if (newVal !== oldVal) {
                    this.localConfig = JSON.parse(newVal);
                }
            }
        }
    },
    methods: {
        normalizeConfig(config) {
            const base = {
                replicator_field: '',
                set: '',
                mappings: []
            };

            if (!config || typeof config !== 'object') {
                return base;
            }

            return {
                replicator_field: config.replicator_field || '',
                set: config.set || '',
                mappings: Array.isArray(config.mappings)
                    ? JSON.parse(JSON.stringify(config.mappings))
                    : []
            };
        },
        addMapping() {
            this.localConfig.mappings.push({
                key: '',
                mode: 'field',
                field: '',
                static: '',
                nested: {
                    replicator_field: '',
                    set: '',
                    mappings: []
                }
            });
        },
        removeMapping(index) {
            this.localConfig.mappings.splice(index, 1);
        },
        sanitizeKey(mapping) {
            mapping.key = mapping.key.replace(/[^a-zA-Z0-9@]/g, '');
        },
        getFieldOptionsForMapping(mapping) {
            if (!this.selectedReplicatorField) {
                return [];
            }

            const sets = this.selectedReplicatorField.sets || [];
            let allFields = [];

            if (this.localConfig.set) {
                const selectedSet = sets.find(set => set.value === this.localConfig.set);
                if (selectedSet && selectedSet.fields) {
                    allFields = selectedSet.fields;
                }
            } else {
                sets.forEach(set => {
                    if (set.fields) {
                        allFields = allFields.concat(set.fields);
                    }
                });
            }

            const uniqueFields = [];
            const seenValues = new Set();
            allFields.forEach(field => {
                if (!seenValues.has(field.value)) {
                    seenValues.add(field.value);
                    uniqueFields.push(field);
                }
            });

            return uniqueFields;
        }
    }
}
</script>

