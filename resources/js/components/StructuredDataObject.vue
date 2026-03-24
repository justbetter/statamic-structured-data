<template>
    <Card class="structured-data-object">
        <div class="mb-4">
            <div class="mb-2">
                <Label class="mb-1.5">@type</Label>
                <Input
                    v-model="objectData.specialProps.type"
                    placeholder="e.g. Organization, Product, BlogPosting"
                />
            </div>

            <div class="mb-2">
                <Label class="mb-1.5">@id</Label>
                <div class="flex">
                    <Input v-model="objectData.specialProps.id" class="flex-1" :placeholder="suggestedId" />
                    <Button class="ml-2" @click="useDefaultId">{{ __('Use Default') }}</Button>
                </div>
            </div>
        </div>

        <div>
            <div v-for="(field, index) in objectData.fields" :key="index" class="mb-4 border-b pb-4">
                <div class="flex items-start gap-2">
                    <div class="flex-1">
                        <Label class="mb-1.5">{{ __('Key') }}</Label>
                        <Input
                            v-model="field.key"
                            @update:model-value="() => validateKey(field)"
                        />
                    </div>

                    <div class="w-32">
                        <Label class="mb-1.5">Type</Label>
                        <Select
                            v-model="field.type"
                            :options="selectOptions"
                            @update:model-value="() => handleTypeChange(field)"
                        />
                    </div>

                    <Button
                        variant="danger"
                        class="mt-6"
                        @click="requestRemoveField(index)"
                    >
                        <Icon name="x" />
                    </Button>
                </div>

                <div class="mt-2">
                    <div v-if="field.type === 'string'">
                        <Input v-model="field.value" />
                    </div>

                    <div v-else-if="field.type === 'numeric'">
                        <Input type="number" v-model="field.value" />
                    </div>

                    <div v-else-if="field.type === 'array'" class="space-y-2">
                        <div v-for="(value, valueIndex) in field.values" :key="valueIndex" class="flex gap-2">
                            <Input v-model="field.values[valueIndex]" />
                            <Button
                                variant="danger"
                                @click="requestRemoveArrayValue(field, valueIndex)"
                            >
                                <Icon name="x" />
                            </Button>
                        </div>
                        <Button @click="addArrayValue(field)" variant="primary">{{ __('Add Value') }}</Button>
                    </div>

                    <div v-else-if="field.type === 'object'" class="mt-2 border rounded p-4">
                        <structured-data-object
                            v-model="field.value"
                            :base-url="baseUrl"
                            :parent-type="objectData.specialProps.type"
                            :field-key="field.key"
                        />
                    </div>

                    <div v-else-if="field.type === 'replicator_object_array'" class="mt-2">
                        <replicator-field-mapper 
                            v-model="field.config" 
                            :replicator-fields="replicatorFields"
                        />
                    </div>
                </div>
            </div>

            <Button @click="addField" variant="primary">{{ __('Add Property') }}</Button>
        </div>

        <ConfirmationModal
            v-if="confirmRemoveFieldOpen"
            v-model:open="confirmRemoveFieldOpen"
            :body-text="__('Are you sure you want to remove this field? This action cannot be undone.')"
            @confirm="confirmRemoveField"
        />

        <ConfirmationModal
            v-if="confirmRemoveArrayValueOpen"
            v-model:open="confirmRemoveArrayValueOpen"
            :body-text="__('Are you sure you want to remove this value? This action cannot be undone.')"
            @confirm="confirmRemoveArrayValue"
        />
    </Card>
</template>

<script>
import { Button, Card, ConfirmationModal, Icon, Input, Label, Select } from '@statamic/cms/ui';
import ReplicatorFieldMapper from './fieldtypes/ReplicatorFieldMapper.vue';

export default {
    name: 'StructuredDataObject',
    components: {
        'replicator-field-mapper': ReplicatorFieldMapper,
        Button,
        Card,
        ConfirmationModal,
        Icon,
        Input,
        Label,
        Select,
    },

    props: {
        modelValue: {
            type: Object,
            default: () => ({
                specialProps: {
                    type: '',
                    id: ''
                },
                fields: []
            })
        },
        baseUrl: {
            type: String,
            required: true
        },
        parentType: {
            type: String,
            default: ''
        },
        fieldKey: {
            type: String,
            default: ''
        }
    },

    data() {
        return {
            objectData: JSON.parse(JSON.stringify(this.modelValue)),
            confirmRemoveFieldOpen: false,
            fieldIndexToRemove: null,
            confirmRemoveArrayValueOpen: false,
            arrayRemoveContext: {
                field: null,
                valueIndex: null,
            },
        };
    },

    computed: {
        suggestedId() {
            if (!this.objectData.specialProps.type) return '';
            return `{{ site:url }}/#${this.objectData.specialProps.type}`;
        },

        selectOptions() {
            return [
                { value: 'string', label: 'String' },
                { value: 'numeric', label: 'Numeric' },
                { value: 'array', label: 'Array' },
                { value: 'object', label: 'Object' },
                { value: 'replicator_object_array', label: 'Replicator Object Array' }
            ];
        },
        availableReplicatorFields() {
            return this.replicatorFields || [];
        }
    },

    watch: {
        objectData: {
            deep: true,
            handler(val) {
                const newVal = JSON.stringify(val);
                const oldVal = JSON.stringify(this.modelValue);
                if (newVal !== oldVal) {
                    this.$emit('update:modelValue', JSON.parse(JSON.stringify(val)));
                }
            }
        },

        'objectData.specialProps.type'(newType) {
            if (!this.objectData.specialProps.id && newType) {
                this.useDefaultId();
            }
        },

        modelValue: {
            deep: true,
            handler(val) {
                const newVal = JSON.stringify(val);
                const oldVal = JSON.stringify(this.objectData);
                if (newVal !== oldVal) {
                    this.objectData = JSON.parse(JSON.stringify(val));
                }
            }
        }
    },

    methods: {
        addField() {
            this.objectData.fields.push({
                key: '',
                type: 'string',
                value: '',
                values: [],
                fields: [],
                config: {}
            });
        },

        removeField(index) {
            this.objectData.fields.splice(index, 1);
        },

        addArrayValue(field) {
            if (!field.values) {
                field.values = [];
            }
            field.values.push('');
        },

        removeArrayValue(field, index) {
            field.values.splice(index, 1);
        },

        validateKey(field) {
            field.key = field.key.replace(/[^a-zA-Z0-9@]/g, '');
        },

        useDefaultId() {
            this.objectData.specialProps.id = this.suggestedId;
        },

        handleTypeChange(field) {
            if (field.type === 'object') {
                this.$set(field, 'value', {
                    specialProps: {
                        type: '',
                        id: ''
                    },
                    fields: []
                });
            } else if (field.type === 'array') {
                field.values = [];
            } else if (field.type === 'replicator_object_array') {
                field.config = {
                    replicator_field: '',
                    set: '',
                    mappings: []
                };
                field.values = [];
            } else {
                field.value = '';
            }
        },

        requestRemoveField(index) {
            this.fieldIndexToRemove = index;
            this.confirmRemoveFieldOpen = true;
        },

        confirmRemoveField() {
            if (this.fieldIndexToRemove === null) {
                return;
            }

            this.removeField(this.fieldIndexToRemove);
            this.fieldIndexToRemove = null;
        },

        requestRemoveArrayValue(field, valueIndex) {
            this.arrayRemoveContext = {
                field,
                valueIndex,
            };
            this.confirmRemoveArrayValueOpen = true;
        },

        confirmRemoveArrayValue() {
            const context = this.arrayRemoveContext;

            if (!context || !context.field || context.valueIndex === null) {
                return;
            }

            this.removeArrayValue(context.field, context.valueIndex);
            this.arrayRemoveContext = {
                field: null,
                valueIndex: null,
            };
        }
    }
}
</script>

<style>
@reference "../../css/statamic-structured-data.css";

.structured-data-object {
    max-width: 800px;
}

.btn-close {
    @apply px-2 py-1 text-gray-500 hover:text-gray-700;
}

.btn {
    @apply bg-gray-200 px-3 py-1 rounded hover:bg-gray-300;
}
</style>
