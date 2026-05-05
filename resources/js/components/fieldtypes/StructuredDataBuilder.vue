<template>
    <div class="structured-data-builder">
        <div class="flex-1">
            <Panel
                v-for="(schema, schemaIndex) in schemas"
                :key="schemaIndex"
                class="structured-data-schema mb-6"
            >
                <PanelHeader class="flex items-center justify-between">
                    <div class="flex items-center gap-2 cursor-pointer" @click="toggleSchema(schemaIndex)">
                        <Heading as="h3" size="lg">
                            <span v-if="schema.specialProps.type">{{ schema.specialProps.type }}</span>
                            <span v-else>{{ __('Schema') }} {{ schemaIndex + 1 }}</span>
                        </Heading>
                    </div>
                    <div class="flex items-center gap-2">
                        <Button
                            v-if="schemas.length > 1"
                            @click="requestRemoveSchema(schemaIndex)"
                            variant="danger"
                        >
                            {{ __('Remove Schema')}}
                        </Button>

                        <Button
                            variant="subtle"
                            @click="toggleSchema(schemaIndex)"
                            :aria-label="isSchemaCollapsed(schemaIndex) ? __('Expand Schema') : __('Collapse Schema')"
                            :title="isSchemaCollapsed(schemaIndex) ? __('Expand Schema') : __('Collapse Schema')"
                        >
                            <Icon :name="isSchemaCollapsed(schemaIndex) ? 'chevron-down' : 'chevron-up'" />
                        </Button>
                    </div>
                </PanelHeader>

                <Card v-show="!isSchemaCollapsed(schemaIndex)">
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
                            <div class="mb-2 flex items-center justify-between">
                                <Heading size="md">{{ __('Fields') }}</Heading>
                                <Button
                                    variant="subtle"
                                    @click="toggleAllFields(schemaIndex, schema)"
                                >
                                    {{ areAllFieldsCollapsed(schemaIndex, schema) ? __('Expand All Fields') : __('Collapse All Fields') }}
                                </Button>
                            </div>

                            <draggable
                                v-model="schema.fields"
                                item-key="__id"
                                @end="onEnd"
                                :key="schemaIndex"
                                handle=".drag-handle"
                            >
                                <template #item="{ element: field, index }">
                                    <Panel class="mb-2">
                                        <PanelHeader class="structured-data-schema-field-header flex justify-between items-center">
                                            <div class="flex items-center gap-4">
                                                <DragHandle class="drag-handle" />
                                                <Heading
                                                    @click="toggleField(schemaIndex, field)"
                                                    as="h6"
                                                    size="xs"
                                                    class="truncate cursor-pointer max-w-[320px]"
                                                >
                                                    {{ getCollapsedFieldSummary(field) }}
                                                </Heading>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <Button
                                                    variant="subtle"
                                                    @click="duplicateField(index, schema)"
                                                    :title="__('Duplicate Field')"
                                                    icon="duplicate"
                                                    icon-only
                                                />

                                                <Button 
                                                    v-show="index > 0" 
                                                    @click="moveFieldUp(index, schema)" 
                                                    :title="__('Move Field Up')" 
                                                    variant="subtle" 
                                                    icon="arrow-up" 
                                                    icon-only 
                                                />

                                                <Button 
                                                    v-show="index < schema.fields.length - 1" 
                                                    @click="moveFieldDown(index, schema)" 
                                                    :title="__('Move Field Down')" 
                                                    variant="subtle" 
                                                    icon="arrow-down" 
                                                    icon-only 
                                                />

                                                <Button
                                                    variant="subtle"
                                                    @click="toggleField(schemaIndex, field)"
                                                    :aria-label="isFieldCollapsed(schemaIndex, field) ? __('Expand Field') : __('Collapse Field')"
                                                >
                                                    <Icon :name="isFieldCollapsed(schemaIndex, field) ? 'chevron-down' : 'chevron-up'" />
                                                </Button>
                                            </div>
                                        </PanelHeader>
                                        <Card v-show="!isFieldCollapsed(schemaIndex, field)">
                                            <StructuredDataFieldEditor
                                                :field="field"
                                                :select-options="selectOptions"
                                                :taxonomy-term-options="taxonomyTermOptions"
                                                :base-url="baseUrl"
                                                :replicator-fields="replicatorFields"
                                                :object-array-enabled="true"
                                                :data-object-enabled="true"
                                                :remove-field-label="__('Remove Field')"
                                                @validate-key="validateKey"
                                                @type-change="handleTypeChange"
                                                @add-array-value="addArrayValue"
                                                @remove-array-value="requestRemoveArrayValue"
                                                @remove-field="requestRemoveField(schema, index)"
                                            />
                                        </Card>
                                    </Panel>
                                </template>
                            </draggable>

                            <Button variant="primary" @click="addField(schema)" class="mt-2">
                                {{ __('Add Field') }}
                            </Button>
                        </div>
                    </div>
                </Card>
            </Panel>

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
import { computed, defineAsyncComponent, ref, watch } from 'vue';
import { Fieldtype } from '@statamic/cms';
import { Button, Card, Heading, Icon, Input, Label, Panel, PanelHeader, DragHandle, ConfirmationModal } from '@statamic/cms/ui';
import PresetStack from '../PresetStack.vue';
import { formatSchemaJson } from '../../utils/schema';
import {
    addStructuredDataArrayValue,
    handleStructuredDataFieldTypeChange,
    removeStructuredDataArrayValue,
    validateStructuredDataKey,
} from '../../composables/useStructuredDataFields';
import draggable from 'vuedraggable';

const StructuredDataFieldEditor = defineAsyncComponent(() => import('../StructuredDataFieldEditor.vue'));

const fieldtypeProps = defineProps(Fieldtype.props);
const { value, meta, config } = fieldtypeProps;

const emit = defineEmits(Fieldtype.emits);
const { expose, update } = Fieldtype.use(emit, fieldtypeProps);

defineExpose(expose);

let nextFieldId = 1;

const createEmptySchema = () => ({
    specialProps: {
        context: 'https://schema.org',
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
const collapsedFields = ref({});
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

const duplicateField = (fieldIndex, schema) => {
    const sourceField = schema?.fields?.[fieldIndex];

    if (!sourceField) {
        return;
    }

    const duplicatedField = JSON.parse(JSON.stringify(sourceField));
    duplicatedField.__id = `field-${nextFieldId}`;
    nextFieldId += 1;

    schema.fields.splice(fieldIndex + 1, 0, duplicatedField);
};

const addArrayValue = field => {
    addStructuredDataArrayValue(field, { objectArrayType: 'object_array' });
};

const removeArrayValue = (field, valueIndex) => {
    removeStructuredDataArrayValue(field, valueIndex);
};

const validateKey = field => {
    validateStructuredDataKey(field);
};

const suggestedId = schema => {
    if (!schema.specialProps.type) return '';
    return `{{ site:url }}/#${schema.specialProps.type}`;
};

const useDefaultId = schema => {
    schema.specialProps.id = suggestedId(schema);
};

const handleTypeChange = field => {
    handleStructuredDataFieldTypeChange(field, {
        objectArrayType: 'object_array',
        dataObjectType: 'data_object',
    });
};

const togglePreview = () => {
    showPreview.value = !showPreview.value;
};

const toggleSchema = schemaIndex => {
    collapsedSchemas.value[schemaIndex] = !collapsedSchemas.value[schemaIndex];
};

const isSchemaCollapsed = schemaIndex => collapsedSchemas.value[schemaIndex] === true;

const getFieldCollapseKey = (schemaIndex, field) => {
    const fieldId = field?.__id ?? '';
    return `${schemaIndex}:${fieldId}`;
};

const toggleField = (schemaIndex, field) => {
    const key = getFieldCollapseKey(schemaIndex, field);
    collapsedFields.value[key] = !collapsedFields.value[key];
};

const isFieldCollapsed = (schemaIndex, field) => {
    const key = getFieldCollapseKey(schemaIndex, field);
    return collapsedFields.value[key] === true;
};

const getCollapsedFieldSummary = field => {
    const key = field?.key?.trim?.() ?? '';

    if (key.length > 0) {
        return key;
    }

    return __('(No key set)');
};

const areAllFieldsCollapsed = (schemaIndex, schema) => {
    if (!schema?.fields?.length) {
        return false;
    }

    return schema.fields.every(field => isFieldCollapsed(schemaIndex, field));
};

const toggleAllFields = (schemaIndex, schema) => {
    if (!schema?.fields?.length) {
        return;
    }

    const nextCollapsedState = !areAllFieldsCollapsed(schemaIndex, schema);

    schema.fields.forEach(field => {
        const key = getFieldCollapseKey(schemaIndex, field);
        collapsedFields.value[key] = nextCollapsedState;
    });
};

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