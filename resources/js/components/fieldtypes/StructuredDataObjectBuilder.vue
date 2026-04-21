<template>
    <div class="structured-data-builder">
        <div class="flex-1">
            <Card
                v-if="schema"
                class="structured-data-schema mb-6"
            >
                <div class="flex items-center gap-2 mb-3 cursor-pointer">
                    <h3 class="font-bold text-lg">{{ __('Object Data Builder') }}</h3>
                </div>
                <div>
                    <div class="space-y-3">
                        <h4 class="text-gray-600 mb-2">{{ __('Fields') }}</h4>
                        <div v-for="(field, index) in schema.fields" :key="index" class="mb-2 border rounded">
                            <div class="p-3">
                                <StructuredDataFieldEditor
                                    :field="field"
                                    :select-options="selectOptions"
                                    :base-url="baseUrl"
                                    :replicator-fields="replicatorFields"
                                    :remove-field-label="__('Remove Field')"
                                    @validate-key="validateKey"
                                    @type-change="handleTypeChange"
                                    @add-array-value="addArrayValue"
                                    @remove-array-value="removeArrayValue"
                                    @remove-field="removeField(index)"
                                />
                            </div>
                        </div>
                        <Button @click="addField()" variant="primary" class="mt-2">
                            {{ __('Add Field') }}
                        </Button>
                    </div>
                </div>
            </Card>

            <div class="flex gap-2 mt-4">
                <Button @click="togglePreview">
                    {{ showPreview ? __('Hide Preview') : __('Show Preview') }}
                </Button>
            </div>

            <div v-if="showPreview" class="mt-4">
                <pre class="p-4 rounded-lg overflow-x-auto">{{ preview }}</pre>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, defineAsyncComponent, ref, watch } from 'vue';
import { Fieldtype } from '@statamic/cms';
import { Button, Card } from '@statamic/cms/ui';
import { formatSchemaJson } from '../../utils/schema';
import {
    addStructuredDataArrayValue,
    handleStructuredDataFieldTypeChange,
    removeStructuredDataArrayValue,
    validateStructuredDataKey,
} from '../../composables/useStructuredDataFields';

const StructuredDataFieldEditor = defineAsyncComponent(() => import('../StructuredDataFieldEditor.vue'));

const fieldtypeProps = defineProps(Fieldtype.props);
const { value, meta, config } = fieldtypeProps;

const emit = defineEmits(Fieldtype.emits);
const { expose, update } = Fieldtype.use(emit, fieldtypeProps);

defineExpose(expose);

const createEmptySchema = () => {
    return {
        fields: [],
    };
};

const initialSchema = value && typeof value === 'object' ? value : createEmptySchema();

const schema = ref(JSON.parse(JSON.stringify(initialSchema)));
const showPreview = ref(false);

const baseUrl = computed(() => {
    if (!config || !config.base_url) {
        return '';
    }

    return config.base_url;
});

const preview = computed(() => {
    return formatSchemaJson(schema.value);
});

const selectOptions = computed(() => {
    return [
        { value: 'string', label: 'String' },
        { value: 'numeric', label: 'Numeric' },
        { value: 'array', label: 'Array' },
        { value: 'object', label: 'Object' },
        { value: 'replicator_object_array', label: 'Replicator Object Array' },
    ];
});

const replicatorFields = computed(() => {
    if (!meta || !meta.replicator_fields) {
        return [];
    }

    return meta.replicator_fields;
});

watch(
    () => value,
    newValue => {
        const normalized = newValue && typeof newValue === 'object' ? newValue : createEmptySchema();
        const newJson = JSON.stringify(normalized);
        const currentJson = JSON.stringify(schema.value);

        if (newJson !== currentJson) {
            schema.value = JSON.parse(newJson);
        }
    },
    { deep: true },
);

watch(
    schema,
    newSchema => {
        update(JSON.parse(JSON.stringify(newSchema)));
    },
    { deep: true },
);

const addField = () => {
    schema.value.fields.push({
        key: '',
        type: 'string',
        value: '',
        values: [],
        fields: [],
    });
};

const removeField = fieldIndex => {
    schema.value.fields.splice(fieldIndex, 1);
};

const addArrayValue = field => {
    addStructuredDataArrayValue(field);
};

const removeArrayValue = (field, valueIndex) => {
    removeStructuredDataArrayValue(field, valueIndex);
};

const validateKey = field => {
    validateStructuredDataKey(field);
};

const handleTypeChange = field => {
    handleStructuredDataFieldTypeChange(field);
};

const togglePreview = () => {
    showPreview.value = !showPreview.value;
};
</script>

<style>
@reference "../../../css/statamic-structured-data.css";

.structured-data-builder {
    max-width: 800px;
}
.btn-close {
    @apply px-2 py-1 text-gray-500 hover:text-gray-700;
}
.btn {
    @apply bg-gray-200 px-3 py-1 rounded hover:bg-gray-300;
}
.chevron {
    width: 10px;
    height: 10px;
    border-right: 2px solid currentColor;
    border-bottom: 2px solid currentColor;
    transform: rotate(45deg);
    transition: transform 0.2s ease;
}
.chevron-up {
    transform: rotate(-135deg);
}
</style>
