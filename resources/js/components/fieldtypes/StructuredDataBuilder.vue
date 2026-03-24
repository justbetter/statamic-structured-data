<template>
    <div class="structured-data-builder">
        <div class="flex-1">
            <Card
                v-for="(schema, schemaIndex) in schemas"
                :key="schemaIndex"
                class="structured-data-schema mb-6"
            >
                <div class="flex justify-between items-center mb-3">
                    <div class="flex items-center gap-2 cursor-pointer" @click="toggleSchema(schemaIndex)">
                        <div class="chevron" :class="{ 'chevron-up': !isSchemaCollapsed(schemaIndex) }"></div>
                        <h3 class="font-bold text-lg">
                            <span v-if="schema.specialProps.type">{{ schema.specialProps.type }}</span>
                            <span v-else>{{ __('Schema') }} {{ schemaIndex + 1 }}</span>
                        </h3>
                    </div>
                    <Button
                        v-if="schemas.length > 1"
                        @click="requestRemoveSchema(schemaIndex)"
                        variant="danger"
                    >
                        {{ __('Remove Schema')}}
                    </Button>
                </div>

                <div v-show="!isSchemaCollapsed(schemaIndex)">
                    <div class="space-y-3">
                        <div v-if="schema.specialProps">
                            <div class="mb-3">
                                <Label class="mb-1.5">@context</Label>
                                <Input v-model="schema.specialProps.context" readonly />
                            </div>

                            <div class="mb-3">
                                <Label class="mb-1.5">@type</Label>
                                <Input
                                    v-model="schema.specialProps.type"
                                    placeholder="e.g. Organization, Product, BlogPosting"
                                />
                            </div>

                            <div class="mb-4">
                                <Label class="mb-1.5">@id</Label>
                                <div class="flex gap-2">
                                    <Input
                                        v-model="schema.specialProps.id"
                                        :placeholder="suggestedId(schema)"
                                    />
                                    <Button @click="useDefaultId(schema)">{{ __('Use Default') }}</Button>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4">
                            <h4 class="text-gray-600 mb-2">{{ __('Fields') }}</h4>

                            <draggable
                                v-model="schema.fields"
                                item-key="__id"
                                @end="onEnd"
                                :key="schemaIndex"
                                handle=".drag-handle"
                            >
                                <template #item="{ element: field, index }">
                                    <Card class="mb-2">
                                        <div class="structured-data-schema-field-header px-2 py-2 flex justify-between items-center">
                                            <div class="flex items-center gap-2">
                                                <DragHandle class="drag-handle" />
                                                <Button v-show="index > 0" @click="moveFieldUp(index, schema)" variant="subtle">↑ {{ __('Move Up') }}</Button>
                                            </div>
                                            <Button v-show="index < schema.fields.length - 1" @click="moveFieldDown(index, schema)" variant="subtle">{{ __('Move Down') }} ↓</Button>
                                        </div>
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
                                                            <Input
                                                                v-model="field.values[valueIndex]"
                                                            />
                                                            <Button
                                                                @click="requestRemoveArrayValue(field, valueIndex)"
                                                                class="inline-flex items-center px-2 py-1"
                                                                variant="danger"
                                                            >
                                                                <span>{{ __('Remove') }}</span>
                                                            </Button>
                                                        </div>
                                                    </div>
                                                    <Button
                                                        @click="addArrayValue(field)"
                                                        variant="primary"
                                                        class="mt-2 text-sm"
                                                    >
                                                        {{ __('Add Value')}}
                                                    </Button>
                                                </div>

                                                <div v-else-if="field.type === 'object'" class="mt-2">
                                                    <structured-data-object 
                                                        v-model="field.value" 
                                                        :base-url="baseUrl"
                                                        :replicator-fields="replicatorFields"
                                                    />
                                                </div>

                                                <div v-else-if="field.type === 'object_array'" class="mt-2">
                                                    <div class="flex flex-col gap-2 space-y-2">
                                                        <div v-for="(value, valueIndex) in field.values" :key="valueIndex" class="flex flex-col gap-2">
                                                            <structured-data-object 
                                                                v-model="field.values[valueIndex]" 
                                                                :base-url="baseUrl"
                                                            />
                                                            <div>
                                                                <Button
                                                                    @click="requestRemoveArrayValue(field, valueIndex)"
                                                                    class="inline-flex self-end items-center px-2 py-1"
                                                                    variant="danger"
                                                                >
                                                                    <span>{{ __('Remove') }}</span>
                                                                </Button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <Button
                                                        @click="addArrayValue(field)"
                                                        class="mt-2 text-sm"
                                                        variant="primary"
                                                    >
                                                        {{ __('Add Value')}}
                                                    </Button>
                                                </div>

                                                <div v-else-if="field.type === 'data_object'" class="mt-2">
                                                    <Select
                                                        v-model="field.value"
                                                        :options="taxonomyTermOptions"
                                                    />
                                                </div>

                                                <div v-else-if="field.type === 'replicator_object_array'" class="mt-2">
                                                    <replicator-field-mapper 
                                                        v-model="field.config"
                                                        :base-url="baseUrl" 
                                                        :replicator-fields="replicatorFields"
                                                    />
                                                </div>
                                            </div>

                                            <div class="flex justify-end mt-3">
                                                <Button variant="danger" @click="requestRemoveField(schema, index)">
                                                    {{ __('Remove Field') }}
                                                </Button>
                                            </div>
                                        </div>
                                    </Card>
                                </template>
                            </draggable>

                            <Button variant="primary" @click="addField(schema)" class="mt-2">
                                {{ __('Add Field') }}
                            </Button>
                        </div>
                    </div>
                </div>
            </Card>

            <div class="flex gap-2 mt-4">
                <Button variant="primary" @click="addSchema">{{ __('Add Schema') }}</Button>
                <Button 
                    v-if="presetsEnabled && presets.length > 0" 
                    @click="showPresetModal = true"
                    variant="primary"
                >
                    {{ __('Add Preset') }}
                </Button>
                <Button @click="togglePreview">
                    {{ showPreview ? __('Hide Preview') : __('Show Preview') }}
                </Button>
            </div>

            <div v-if="showPreview" class="mt-4">
                <pre class="p-4 rounded-lg overflow-x-auto">{{ preview }}</pre>
            </div>
        </div>

        <PresetStack
            :visible="showPresetModal"
            :presets="presets"
            :has-existing-schemas="schemas.length > 0"
            @close="showPresetModal = false"
            @preset-selected="handlePresetSelected"
        />

        <ConfirmationModal
            v-model:open="confirmRemoveSchemaOpen"
            :body-text="__('Are you sure you want to remove this schema? This action cannot be undone.')"
            @confirm="confirmRemoveSchema"
        />

        <ConfirmationModal
            v-model:open="confirmRemoveFieldOpen"
            :body-text="__('Are you sure you want to remove this field? This action cannot be undone.')"
            @confirm="confirmRemoveField"
        />

        <ConfirmationModal
            v-model:open="confirmRemoveArrayValueOpen"
            :body-text="__('Are you sure you want to remove this value? This action cannot be undone.')"
            @confirm="confirmRemoveArrayValue"
        />
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { Fieldtype } from '@statamic/cms';
import { Button, Card, Input, Label, Select, DragHandle, ConfirmationModal } from '@statamic/cms/ui';
import StructuredDataObject from '../StructuredDataObject.vue';
import ReplicatorFieldMapper from './ReplicatorFieldMapper.vue';
import PresetStack from '../PresetStack.vue';
import { formatSchemaJson } from '../../utils/schema';
import draggable from 'vuedraggable';

const fieldtypeProps = defineProps(Fieldtype.props);
const { value, meta, config } = fieldtypeProps;

const emit = defineEmits(Fieldtype.emits);
const { expose, update } = Fieldtype.use(emit, fieldtypeProps);

defineExpose(expose);

let nextFieldId = 1;

const createEmptySchema = () => ({
    specialProps: {
        context: 'http://schema.org',
        type: '',
        id: '',
    },
    fields: [],
});

const ensureFieldIds = schemasToNormalize => {
    schemasToNormalize.forEach(schema => {
        schema.fields.forEach(field => {
            if (!field.__id) {
                field.__id = `field-${nextFieldId}`;
                nextFieldId += 1;
            }
        });
    });
};

const initialSchemas = Array.isArray(value) && value.length > 0
    ? value
    : [createEmptySchema()];

ensureFieldIds(initialSchemas);

const schemas = ref(JSON.parse(JSON.stringify(initialSchemas)));
const showPreview = ref(false);
const showPresetModal = ref(false);
const collapsedSchemas = ref({});
const confirmRemoveSchemaOpen = ref(false);
const schemaIndexToRemove = ref(null);
const confirmRemoveFieldOpen = ref(false);
const fieldRemoveContext = ref({ schema: null, fieldIndex: null });
const confirmRemoveArrayValueOpen = ref(false);
const arrayRemoveContext = ref({ field: null, valueIndex: null });

const baseUrl = computed(() => config?.base_url ?? '');
const preview = computed(() => formatSchemaJson(schemas.value));
const selectOptions = computed(() => [
    { value: 'string', label: 'String' },
    { value: 'numeric', label: 'Numeric' },
    { value: 'array', label: 'Array' },
    { value: 'object', label: 'Object' },
    { value: 'object_array', label: 'Object Array' },
    { value: 'data_object', label: 'Data Object (Term)' },
    { value: 'replicator_object_array', label: 'Replicator Object Array' },
]);
const taxonomyTermOptions = computed(() => (meta?.taxonomy_terms ?? []).map(term => ({
    label: term.title,
    value: `@dataObject::${term.slug}`,
})));
const presets = computed(() => meta?.presets ?? []);
const presetsEnabled = computed(() => meta?.presets_enabled === true);
const replicatorFields = computed(() => meta?.replicator_fields ?? []);

watch(
    () => value,
    newValue => {
        const normalized = Array.isArray(newValue) && newValue.length > 0
            ? newValue
            : [createEmptySchema()];

        ensureFieldIds(normalized);

        const newJson = JSON.stringify(normalized);
        const currentJson = JSON.stringify(schemas.value);

        if (newJson !== currentJson) {
            schemas.value = JSON.parse(newJson);
        }
    },
    { deep: true },
);

watch(
    schemas,
    newSchemas => {
        ensureFieldIds(newSchemas);
        update(JSON.parse(JSON.stringify(newSchemas)));
    },
    { deep: true },
);

const addSchema = () => {
    schemas.value.push(createEmptySchema());
};

const addField = schema => {
    schema.fields.push({
        __id: `field-${nextFieldId}`,
        key: '',
        type: 'string',
        value: '',
        values: [],
        fields: [],
        config: {},
    });
    nextFieldId += 1;
};

const moveFieldUp = (fieldIndex, schema) => {
    if (fieldIndex <= 0) return;

    const updatedFields = [...schema.fields];
    const previousField = updatedFields[fieldIndex - 1];

    updatedFields[fieldIndex - 1] = updatedFields[fieldIndex];
    updatedFields[fieldIndex] = previousField;

    schema.fields = updatedFields;
};

const moveFieldDown = (fieldIndex, schema) => {
    if (fieldIndex >= schema.fields.length - 1) return;

    const updatedFields = [...schema.fields];
    const nextField = updatedFields[fieldIndex + 1];

    updatedFields[fieldIndex + 1] = updatedFields[fieldIndex];
    updatedFields[fieldIndex] = nextField;

    schema.fields = updatedFields;
};

const removeField = (schema, fieldIndex) => {
    schema.fields.splice(fieldIndex, 1);
};

const addArrayValue = field => {
    if (!field.values) {
        field.values = [];
    }

    if (field.type === 'object_array') {
        field.values.push({ specialProps: { type: '', id: '' }, fields: [] });
        return;
    }

    field.values.push('');
};

const removeArrayValue = (field, valueIndex) => {
    field.values.splice(valueIndex, 1);
};

const validateKey = field => {
    field.key = field.key.replace(/[^a-zA-Z0-9@]/g, '');
};

const suggestedId = schema => {
    if (!schema.specialProps.type) return '';
    return `{{ site:url }}/#${schema.specialProps.type}`;
};

const useDefaultId = schema => {
    schema.specialProps.id = suggestedId(schema);
};

const handleTypeChange = field => {
    if (field.type === 'object') {
        field.value = { specialProps: { type: '', id: '' }, fields: [] };
        return;
    }
    if (field.type === 'array') {
        field.values = [];
        return;
    }
    if (field.type === 'object_array') {
        field.values = [];
        return;
    }
    if (field.type === 'data_object') {
        field.value = '';
        return;
    }
    if (field.type === 'replicator_object_array') {
        field.config = { replicator_field: '', set: '', mappings: [] };
        field.values = [];
        return;
    }
    field.value = '';
};

const togglePreview = () => {
    showPreview.value = !showPreview.value;
};

const toggleSchema = schemaIndex => {
    collapsedSchemas.value[schemaIndex] = !collapsedSchemas.value[schemaIndex];
};

const isSchemaCollapsed = schemaIndex => collapsedSchemas.value[schemaIndex] === true;

const requestRemoveSchema = schemaIndex => {
    schemaIndexToRemove.value = schemaIndex;
    confirmRemoveSchemaOpen.value = true;
};

const confirmRemoveSchema = () => {
    if (schemaIndexToRemove.value === null) return;

    schemas.value.splice(schemaIndexToRemove.value, 1);
    schemaIndexToRemove.value = null;
    confirmRemoveSchemaOpen.value = false;
};

const requestRemoveField = (schema, fieldIndex) => {
    fieldRemoveContext.value = { schema, fieldIndex };
    confirmRemoveFieldOpen.value = true;
};

const confirmRemoveField = () => {
    const context = fieldRemoveContext.value;

    if (!context || !context.schema || context.fieldIndex === null) return;

    removeField(context.schema, context.fieldIndex);
    fieldRemoveContext.value = { schema: null, fieldIndex: null };
    confirmRemoveFieldOpen.value = false;
};

const requestRemoveArrayValue = (field, valueIndex) => {
    arrayRemoveContext.value = { field, valueIndex };
    confirmRemoveArrayValueOpen.value = true;
};

const confirmRemoveArrayValue = () => {
    const context = arrayRemoveContext.value;

    if (!context || !context.field || context.valueIndex === null) return;

    removeArrayValue(context.field, context.valueIndex);
    arrayRemoveContext.value = { field: null, valueIndex: null };
    confirmRemoveArrayValueOpen.value = false;
};

const handlePresetSelected = presetEvent => {
    const { preset, action } = presetEvent;
    const presetSchema = JSON.parse(JSON.stringify(preset.schema));

    if (action === 'merge' || action === 'add') {
        schemas.value.push(presetSchema);
        return;
    }

    if (action === 'override') {
        schemas.value = [presetSchema];
    }
};

const onEnd = dragEvent => dragEvent;
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

.btn-preset {
    @apply bg-purple-500 text-white px-4 py-2 rounded hover:bg-purple-600 font-medium transition-colors;
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