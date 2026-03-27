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
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                    <div class="col-span-2">
                                        <Label class="mb-1.5">{{ __('Key') }}</Label>
                                        <Input
                                            v-model="field.key"
                                            @update:model-value="() => validateKey(field)"
                                        />
                                    </div>
                                    <div>
                                        <Label class="mb-1.5">{{ __('Type') }}</Label>
                                        <Select
                                            v-model="field.type"
                                            :options="selectOptions"
                                            @update:model-value="() => handleTypeChange(field)"
                                        />
                                    </div>
                                </div>
                                <div class="mt-3">
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
                                                <Input
                                                    v-model="field.values[valueIndex]"
                                                />
                                                <Button
                                                    @click="removeArrayValue(field, valueIndex)"
                                                    class="inline-flex items-center px-2 py-1"
                                                >
                                                    <span>{{ __('Remove') }}</span>
                                                </Button>
                                            </div>
                                        </div>
                                        <Button
                                            @click="addArrayValue(field)"
                                            class="mt-2 text-sm"
                                            variant="primary"
                                        >
                                            {{ __('Add Value') }}
                                        </Button>
                                    </div>
                                    <div v-else-if="field.type === 'object'" class="mt-2">
                                        <structured-data-object
                                            v-model="field.value"
                                            :base-url="baseUrl"
                                            :field-key="field.key"
                                            :replicator-fields="replicatorFields"
                                        />
                                    </div>
                                    <div v-else-if="field.type === 'replicator_object_array'" class="mt-2">
                                        <replicator-field-mapper 
                                            v-model="field.config" 
                                            :replicator-fields="replicatorFields"
                                        />
                                    </div>
                                </div>
                                <div class="flex justify-end mt-3">
                                    <Button
                                        @click="removeField(index)"
                                    >
                                        {{ __('Remove Field') }}
                                    </Button>
                                </div>
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
import { computed, ref, watch } from 'vue';
import { Fieldtype } from '@statamic/cms';
import { Button, Card, Input, Label, Select } from '@statamic/cms/ui';
import StructuredDataObject from '../StructuredDataObject.vue';
import ReplicatorFieldMapper from './ReplicatorFieldMapper.vue';
import { formatSchemaJson } from '../../utils/schema';

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
    if (!field.values) {
        // eslint-disable-next-line no-param-reassign
        field.values = [];
    }

    field.values.push('');
};

const removeArrayValue = (field, valueIndex) => {
    field.values.splice(valueIndex, 1);
};

const validateKey = field => {
    field.key = field.key.replace(/[^a-zA-Z0-9@:-]/g, '');
};

const handleTypeChange = field => {
    if (field.type === 'object') {
        // eslint-disable-next-line no-param-reassign
        field.value = {
            specialProps: {
                type: '',
                id: '',
            },
            fields: [],
        };

        return;
    }

    if (field.type === 'array') {
        // eslint-disable-next-line no-param-reassign
        field.values = [];

        return;
    }

    if (field.type === 'replicator_object_array') {
        // eslint-disable-next-line no-param-reassign
        field.config = {
            replicator_field: '',
            set: '',
            mappings: [],
        };
        // eslint-disable-next-line no-param-reassign
        field.values = [];

        return;
    }

    // eslint-disable-next-line no-param-reassign
    field.value = '';
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
