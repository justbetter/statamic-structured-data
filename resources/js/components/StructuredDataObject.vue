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
                <StructuredDataFieldEditor
                    :field="field"
                    :select-options="selectOptions"
                    :base-url="baseUrl"
                    :replicator-fields="replicatorFields"
                    :remove-field-label="__('Remove Property')"
                    @validate-key="validateKey"
                    @type-change="handleTypeChange"
                    @add-array-value="addArrayValue"
                    @remove-array-value="requestRemoveArrayValue"
                    @remove-field="requestRemoveField(index)"
                />
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
import { defineAsyncComponent } from 'vue';
import { Button, Card, ConfirmationModal, Input, Label } from '@statamic/cms/ui';
import {
    addStructuredDataArrayValue,
    handleStructuredDataFieldTypeChange,
    removeStructuredDataArrayValue,
    validateStructuredDataKey,
} from '../composables/useStructuredDataFields';

export default {
    name: 'StructuredDataObject',
    components: {
        Button,
        Card,
        ConfirmationModal,
        Input,
        Label,
        StructuredDataFieldEditor: defineAsyncComponent(() => import('./StructuredDataFieldEditor.vue')),
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
        },
        replicatorFields: {
            type: Array,
            default: () => []
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
            addStructuredDataArrayValue(field);
        },

        removeArrayValue(field, index) {
            removeStructuredDataArrayValue(field, index);
        },

        validateKey(field) {
            validateStructuredDataKey(field);
        },

        useDefaultId() {
            this.objectData.specialProps.id = this.suggestedId;
        },

        handleTypeChange(field) {
            handleStructuredDataFieldTypeChange(field);
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
