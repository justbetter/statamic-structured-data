<template>
    <div class="replicator-mapper space-y-3">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div>
                <Label class="mb-1.5">{{ __('Replicator Field') }}</Label>
                <Select
                    v-model="localConfig.replicator_field"
                    :options="replicatorFieldOptions"
                    @update:model-value="() => { localConfig.set = ''; }"
                    :placeholder="replicatorFieldOptions.length > 0 ? __('Select replicator field') : __('No replicator fields available')"
                    :disabled="replicatorFieldOptions.length === 0"
                />
                <div v-if="replicatorFieldOptions.length === 0" class="text-xs text-yellow-600 mt-1 bg-yellow-50 p-2 rounded">
                    <strong>{{ __('No replicator fields found.') }}</strong><br>
                    {{ __('Make sure your Structured Data Template has "Use for collection" or "Use for taxonomy" set, and that collection/taxonomy has replicator fields in its blueprint.') }}
                </div>
            </div>
            <div>
                <Label class="mb-1.5">{{ __('Limit to Set (optional)') }}</Label>
                <Select
                    v-model="localConfig.set"
                    :options="setOptions"
                    :placeholder="__('All sets')"
                    :clearable="true"
                />
            </div>
        </div>

        <Panel>
            <PanelHeader>
                <div class="flex flex-col justify-center gap-2">
                    <span>{{ __('Flat mode') }}</span>
                
                    <Description>
                        <Checkbox 
                            v-model="localConfig.flat" 
                            :label="__('Create a flat object where each replicator row becomes a key-value pair.')" 
                            class="items-center"
                        />
                    </Description>
                </div>
            </PanelHeader>

            <Card v-if="localConfig.flat">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <Label class="mb-1.5">{{ __('Key Field') }}</Label>
                        <Select
                            v-model="localConfig.flat_key_field"
                            :options="flatFieldOptions"
                            :placeholder="__('Select field to use as key')"
                        />
                        <p class="text-xs text-gray-500 mt-1">{{ __('Field value will be used as the object key') }}</p>
                    </div>
                    <div>
                        <Label class="mb-1.5">{{ __('Value Field') }}</Label>
                        <Select
                            v-model="localConfig.flat_value_field"
                            :options="flatFieldOptions"
                            :placeholder="__('Select field to use as value')"
                        />
                        <p class="text-xs text-gray-500 mt-1">{{ __('Field value will be used as the object value') }}</p>
                    </div>
                </div>
            </Card>
        </Panel>

        <Card v-if="!localConfig.flat" class="mt-3">
            <div class="flex items-center justify-between mb-2">
                <h4 class="font-semibold text-gray-700">{{ __('Field Mappings') }}</h4>
                <Button class="text-sm" @click="addMapping">{{ __('Add Mapping') }}</Button>
            </div>

            <div v-if="!localConfig.mappings.length" class="text-sm text-gray-500">
                {{ __('No mappings yet. Add one to map replicator fields into your JSON-LD object.') }}
            </div>

            <Card
                v-for="(mapping, index) in localConfig.mappings"
                :key="index"
                class="mb-3"
            >
                <div class="flex items-start gap-2">
                    <div class="flex-1">
                        <Label class="mb-1.5">{{ __('JSON-LD Key') }}</Label>
                        <Input
                            v-model="mapping.key"
                            placeholder="e.g. name"
                            @update:model-value="() => sanitizeKey(mapping)"
                        />
                    </div>
                    <div class="w-40">
                        <Label class="mb-1.5">{{ __('Source') }}</Label>
                        <Select
                            v-model="mapping.mode"
                            :options="modeOptions"
                        />
                    </div>
                    <Button class="mt-6" variant="danger" @click="requestRemoveMapping(index)">{{ __('Remove') }}</Button>
                </div>

                <div class="mt-2">
                    <template v-if="mapping.mode === 'static'">
                        <Label class="mb-1.5">{{ __('Static Value') }}</Label>
                        <Input
                            v-model="mapping.static"
                            :placeholder="__('e.g. PropertyValue')"
                        />
                    </template>

                    <template v-else-if="mapping.mode === 'field'">
                        <Label class="mb-1.5">{{ __('Replicator Field') }}</Label>
                        <Select
                            v-model="mapping.field"
                            :options="getFieldOptionsForMapping(mapping)"
                            :placeholder="__('Select field')"
                        />
                    </template>

                    <template v-else-if="mapping.mode === 'nested_replicator'">
                        <Card class="mt-2">
                            <replicator-field-mapper
                                v-model="mapping.nested"
                                :replicator-fields="replicatorFields"
                            />
                        </Card>
                    </template>
                </div>
            </Card>
        </Card>

        <ConfirmationModal
            v-if="confirmRemoveMappingOpen"
            v-model:open="confirmRemoveMappingOpen"
            :body-text="__('Are you sure you want to remove this mapping? This action cannot be undone.')"
            @confirm="confirmRemoveMapping"
        />
    </div>
</template>

<script>
import { Button, Card, Checkbox, ConfirmationModal, Description, Heading, Input, Label, Panel, PanelHeader, Select } from '@statamic/cms/ui';

export default {
    name: 'ReplicatorFieldMapper',
    components: {
        Button,
        Card,
        Checkbox,
        Description,
        Heading,
        Input,
        Label,
        Panel,
        PanelHeader,
        Select,
    },
    props: {
        modelValue: {
            type: Object,
            default: () => ({
                replicator_field: '',
                set: '',
                mappings: [],
                flat: false,
                flat_key_field: '',
                flat_value_field: '',
            }),
        },
        replicatorFields: {
            type: Array,
            default: () => [],
        },
    },
    emits: ['update:modelValue'],
    data() {
        return {
            localConfig: this.normalizeConfig(this.modelValue),
            modeOptions: [
                { value: 'field', label: 'From Replicator Field' },
                { value: 'static', label: 'Static Value' },
                { value: 'nested_replicator', label: 'Nested Replicator' },
            ],
            confirmRemoveMappingOpen: false,
            mappingIndexToRemove: null,
        };
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
        },
        flatFieldOptions() {
            return this.getFieldOptionsForMapping({});
        }
    },
    watch: {
        localConfig: {
            deep: true,
            handler(val) {
                const newVal = JSON.stringify(val);
                const oldVal = JSON.stringify(this.modelValue);

                if (newVal !== oldVal) {
                    this.$emit('update:modelValue', JSON.parse(newVal));
                }
            },
        },
        modelValue: {
            deep: true,
            handler(val) {
                const normalized = this.normalizeConfig(val);
                const newVal = JSON.stringify(normalized);
                const oldVal = JSON.stringify(this.localConfig);

                if (newVal !== oldVal) {
                    this.localConfig = JSON.parse(newVal);
                }
            },
        },
    },
    methods: {
        normalizeConfig(config) {
            const base = {
                replicator_field: '',
                set: '',
                mappings: [],
                flat: false,
                flat_key_field: '',
                flat_value_field: ''
            };

            if (!config || typeof config !== 'object') {
                return base;
            }

            return {
                replicator_field: config.replicator_field || '',
                set: config.set || '',
                mappings: Array.isArray(config.mappings)
                    ? JSON.parse(JSON.stringify(config.mappings))
                    : [],
                flat: config.flat === true,
                flat_key_field: config.flat_key_field || '',
                flat_value_field: config.flat_value_field || '',
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
        requestRemoveMapping(index) {
            this.mappingIndexToRemove = index;
            this.confirmRemoveMappingOpen = true;
        },
        confirmRemoveMapping() {
            if (this.mappingIndexToRemove === null) {
                return;
            }

            this.removeMapping(this.mappingIndexToRemove);
            this.mappingIndexToRemove = null;
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

