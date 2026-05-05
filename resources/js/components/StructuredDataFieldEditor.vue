<template>
    <div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div class="col-span-2">
                <Label class="mb-1.5">{{ __('Key') }}</Label>
                <Input
                    v-model="field.key"
                    @update:model-value="emit('validate-key', field)"
                />
            </div>

            <div>
                <Label class="mb-1.5">{{ __('Type') }}</Label>
                <Select
                    v-model="field.type"
                    :options="selectOptions"
                    @update:model-value="emit('type-change', field)"
                />
            </div>
        </div>

        <div class="mt-3">
            <Label class="mb-1.5">{{ __('Value') }}</Label>

            <Input
                v-if="field.type === 'string'"
                v-model="field.value"
                :placeholder="'Enter value'"
            />

            <Input
                v-if="field.type === 'numeric'"
                type="number"
                v-model="field.value"
                :placeholder="'Enter value'"
            />

            <div v-else-if="field.type === 'array'" class="mt-2">
                <div class="flex flex-col gap-2 space-y-2">
                    <div v-for="(value, valueIndex) in field.values" :key="valueIndex" class="flex items-center gap-2">
                        <Input v-model="field.values[valueIndex]" />
                        <Button
                            @click="emit('remove-array-value', field, valueIndex)"
                            class="inline-flex items-center px-2 py-1"
                            variant="danger"
                        >
                            <span>{{ __('Remove') }}</span>
                        </Button>
                    </div>
                </div>
                <Button
                    @click="emit('add-array-value', field)"
                    variant="primary"
                    class="mt-2 text-sm"
                >
                    {{ __('Add Value') }}
                </Button>
            </div>

            <div v-else-if="field.type === 'object'" class="mt-2">
                <StructuredDataObject
                    v-model="field.value"
                    :base-url="baseUrl"
                    :field-key="field.key"
                    :replicator-fields="replicatorFields"
                />
            </div>

            <div v-else-if="objectArrayEnabled && field.type === 'object_array'" class="mt-2">
                <div class="flex flex-col gap-2 space-y-2">
                    <div v-for="(value, valueIndex) in field.values" :key="valueIndex" class="flex flex-col gap-2">
                        <StructuredDataObject
                            v-model="field.values[valueIndex]"
                            :base-url="baseUrl"
                        />
                        <div>
                            <Button
                                @click="emit('remove-array-value', field, valueIndex)"
                                class="inline-flex self-end items-center px-2 py-1"
                                variant="danger"
                            >
                                <span>{{ __('Remove') }}</span>
                            </Button>
                        </div>
                    </div>
                </div>
                <Button
                    @click="emit('add-array-value', field)"
                    class="mt-2 text-sm"
                    variant="primary"
                >
                    {{ __('Add Value') }}
                </Button>
            </div>

            <div v-else-if="dataObjectEnabled && field.type === 'data_object'" class="mt-2">
                <Select
                    v-model="field.value"
                    :options="taxonomyTermOptions"
                />
            </div>

            <div v-else-if="field.type === 'replicator_object_array'" class="mt-2">
                <ReplicatorFieldMapper
                    v-model="field.config"
                    :base-url="baseUrl"
                    :replicator-fields="replicatorFields"
                />
            </div>
        </div>

        <div v-if="showRemoveButton" class="flex justify-end mt-3">
            <Button variant="danger" @click="emit('remove-field')">
                {{ removeFieldLabel }}
            </Button>
        </div>
    </div>
</template>

<script setup>
import { Button, Input, Label, Select } from '@statamic/cms/ui';
import ReplicatorFieldMapper from './fieldtypes/ReplicatorFieldMapper.vue';
import StructuredDataObject from './StructuredDataObject.vue';

defineProps({
    field: {
        type: Object,
        required: true,
    },
    selectOptions: {
        type: Array,
        required: true,
    },
    taxonomyTermOptions: {
        type: Array,
        default: () => [],
    },
    baseUrl: {
        type: String,
        default: '',
    },
    replicatorFields: {
        type: Array,
        default: () => [],
    },
    objectArrayEnabled: {
        type: Boolean,
        default: false,
    },
    dataObjectEnabled: {
        type: Boolean,
        default: false,
    },
    showRemoveButton: {
        type: Boolean,
        default: true,
    },
    removeFieldLabel: {
        type: String,
        default: 'Remove Field',
    },
});

const emit = defineEmits([
    'validate-key',
    'type-change',
    'add-array-value',
    'remove-array-value',
    'remove-field',
]);
</script>
