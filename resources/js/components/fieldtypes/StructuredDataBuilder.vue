<template>
    <div class="structured-data-builder">
        <div class="flex-1">
            <div v-for="(schema, schemaIndex) in schemas" :key="schemaIndex" class="mb-6 bg-white rounded-lg shadow-sm border">
                <div class="px-4 py-2 bg-gray-50 border-b rounded-t-lg flex justify-between items-center">
                    <div class="flex items-center gap-2 cursor-pointer" @click="toggleSchema(schemaIndex)">
                        <div class="chevron" :class="{ 'chevron-up': !isSchemaCollapsed(schemaIndex) }"></div>
                        <h3 class="font-bold text-lg">{{ __('Schema') }} {{ schemaIndex + 1 }}</h3>
                    </div>
                    <button
                        v-if="schemas.length > 1"
                            @click="removeSchema(schemaIndex)"
                            class="btn-danger"
                        >
                        {{ __('Remove Schema')}}
                    </button>
                </div>

                <div v-show="!isSchemaCollapsed(schemaIndex)" class="p-4">
                    <div class="space-y-3">
                        <div v-if="schema.specialProps">
                            <div class="mb-3">
                                <label class="text-gray-600 mb-1 block">@context</label>
                                <input type="text" v-model="schema.specialProps.context" class="input-text w-full bg-gray-50" readonly />
                            </div>

                            <div class="mb-3">
                                <label class="text-gray-600 mb-1 block">@type</label>
                                <input
                                    type="text"
                                    v-model="schema.specialProps.type"
                                    class="input-text w-full"
                                    placeholder="e.g. Organization, Product, BlogPosting"
                                />
                            </div>

                            <div class="mb-4">
                                <label class="text-gray-600 mb-1 block">@id</label>
                                <div class="flex gap-2">
                                    <input
                                        type="text"
                                        v-model="schema.specialProps.id"
                                        class="input-text flex-1"
                                        :placeholder="suggestedId(schema)"
                                    />
                                    <button class="btn-primary" @click="useDefaultId(schema)">Use Default</button>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4">
                            <h4 class="text-gray-600 mb-2">{{ __('Fields') }}</h4>

                            <draggable v-model="schema.fields" @end="onEnd" :key="schemaIndex" handle=".drag-handle">
                                <div v-for="(field, index) in schema.fields" :key="index" class="mb-2 border rounded bg-gray-50">
                                    <div class="px-2 py-2 bg-gray-100 flex justify-between items-center border-b rounded-t-lg cursor-drag drag-handle">
                                        <button v-show="index > 0" @click="moveFieldUp(index, schema)" class="btn btn-secondary">↑ Move Up</button>
                                        <button v-show="index < schema.fields.length - 1" @click="moveFieldDown(index, schema)" class="btn btn-secondary">Move Down ↓</button>
                                    </div>
                                    <div class="p-3">
                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                            <div class="col-span-2">
                                                <label class="text-gray-600 mb-1 block">{{ __('Key') }}</label>
                                                <input
                                                    type="text"
                                                    v-model="field.key"
                                                    class="input-text w-full"
                                                    @input="validateKey(field)"
                                                />
                                            </div>

                                            <div>
                                                <label class="text-gray-600 mb-1 block">{{ __('Type') }}</label>
                                                <v-select
                                                    v-model="field.type"
                                                    :options="selectOptions"
                                                    @input="(value) => { field.type = value.value; handleTypeChange(field); }"
                                                />
                                            </div>
                                        </div>

                                        <div class="mt-3">
                                            <input
                                                v-if="field.type === 'string'"
                                                type="text"
                                                v-model="field.value"
                                                class="input-text w-full"
                                                :placeholder="'Enter value'"
                                            />

                                            <div v-else-if="field.type === 'array'" class="mt-2">
                                                <div class="flex flex-col gap-2 space-y-2">
                                                    <div v-for="(value, valueIndex) in field.values" :key="valueIndex" class="flex items-center gap-2">
                                                        <input
                                                            type="text"
                                                            v-model="field.values[valueIndex]"
                                                            class="input-text flex-1"
                                                        />
                                                        <button
                                                            @click="removeArrayValue(field, valueIndex)"
                                                            class="btn-danger inline-flex items-center px-2 py-1"
                                                        >
                                                            <span>{{ __('Remove') }}</span>
                                                        </button>
                                                    </div>
                                                </div>
                                                <button
                                                    @click="addArrayValue(field)"
                                                    class="btn-primary mt-2 text-sm"
                                                >
                                                    {{ __('Add Value')}}
                                                </button>
                                            </div>

                                            <div v-else-if="field.type === 'object'" class="mt-2">
                                                <structured-data-object v-model="field.value" />
                                            </div>

                                            <div v-else-if="field.type === 'object_array'" class="mt-2">
                                                <div class="flex flex-col gap-2 space-y-2">
                                                    <div v-for="(value, valueIndex) in field.values" :key="valueIndex" class="flex flex-col gap-2">
                                                        <structured-data-object v-model="field.values[valueIndex]" />
                                                        <button
                                                            @click="removeArrayValue(field, valueIndex)"
                                                            class="btn-danger inline-flex self-end items-center px-2 py-1"
                                                        >
                                                            <span>{{ __('Remove') }}</span>
                                                        </button>
                                                    </div>
                                                </div>
                                                <button
                                                    @click="addArrayValue(field)"
                                                    class="btn-primary mt-2 text-sm"
                                                >
                                                    {{ __('Add Value')}}
                                                </button>
                                            </div>

                                            <div v-else-if="field.type === 'data_object'" class="mt-2">
                                                 <v-select
                                                    v-model="field.value"
                                                    :options="taxonomyTermOptions"
                                                    @input="(value) => { field.value = value.value; }"
                                                />
                                            </div>
                                        </div>

                                        <div class="flex justify-end mt-3">
                                            <button
                                                @click="removeField(schema, index)"
                                                class="btn-danger"
                                            >
                                                {{ __('Remove Field') }}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </draggable>

                            <button @click="addField(schema)" class="btn-primary mt-2">
                                {{ __('Add Field') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex gap-2 mt-4">
                <button class="btn-primary" @click="addSchema">{{ __('Add Schema') }}</button>
                <button class="btn" @click="togglePreview">
                    {{ showPreview ? __('Hide Preview') : __('Show Preview') }}
                </button>
            </div>

            <div v-if="showPreview" class="mt-4">
                <pre class="bg-gray-50 p-4 rounded-lg overflow-x-auto">{{ preview }}</pre>
            </div>
        </div>
    </div>
</template>

<script>
import StructuredDataObject from '../StructuredDataObject.vue';
import { formatSchemaJson } from '../../utils/schema';
import draggable from 'vuedraggable';

export default {
    name: 'StructuredDataBuilder',
    mixins: [Fieldtype],

    components: {
        'structured-data-object': StructuredDataObject,
        draggable,
    },

    props: {
        value: {
            type: Array,
            default: () => [{
                specialProps: {
                    context: 'http://schema.org',
                    type: '',
                    id: ''
                },
                fields: []
            }]
        },
        baseUrl: {
            type: String,
            default: ''
        },
        meta: {
            type: Object,
            default: () => ({})
        }
    },

    data() {
        return {
            schemas: this.value.length ? this.value : [{
                specialProps: {
                    context: 'http://schema.org',
                    type: '',
                    id: ''
                },
                fields: []
            }],
            showPreview: false,
            collapsedSchemas: {}
        }
    },

    computed: {
        baseUrl() {
            return this.config?.base_url || '';
        },

        preview() {
            return formatSchemaJson(this.schemas);
        },

        selectOptions() {
            return [
                { value: 'string', label: 'String' },
                { value: 'array', label: 'Array' },
                { value: 'object', label: 'Object' },
                { value: 'object_array', label: 'Object Array' },
                { value: 'data_object', label: 'Data Object (Term)' }
            ];
        },

        taxonomyTerms() {
            return this.meta?.taxonomy_terms || [];
        },

        taxonomyTermOptions() {
            return this.taxonomyTerms.map(term => {
                return {
                    label: term.title,
                    value: '@dataObject::' + term.slug
                };
            });
        }
    },

    watch: {
        schemas: {
            deep: true,
            handler(val) {
                this.$emit('input', val);
            }
        }
    },

    methods: {
        addSchema() {
            this.schemas.push({
                specialProps: {
                    context: 'http://schema.org',
                    type: '',
                    id: ''
                },
                fields: []
            });
        },

        addField(schema) {
            schema.fields.push({
                key: '',
                type: 'string',
                value: '',
                values: [],
                fields: []
            });
        },

        moveFieldUp(index, schema) {
            if (index > 0) {
                const fields = [...schema.fields];
                const temp = fields[index - 1];
                fields[index - 1] = fields[index];
                fields[index] = temp;
                schema.fields = fields;
            }
        },
        moveFieldDown(index, schema) {
            if (index < schema.fields.length - 1) {
                const fields = [...schema.fields];
                const temp = fields[index + 1];
                fields[index + 1] = fields[index];
                fields[index] = temp;
                schema.fields = fields;
            }
        },

        removeField(schema, index) {
            schema.fields.splice(index, 1);
        },

        addArrayValue(field) {
            if (!field.values) {
                field.values = [];
            }
            if (field.type === 'object_array') {
                field.values.push({
                    specialProps: {
                        type: '',
                        id: ''
                    },
                    fields: []
                });
            } else {
                field.values.push('');
            }
        },

        removeArrayValue(field, index) {
            field.values.splice(index, 1);
        },

        validateKey(field) {
            field.key = field.key.replace(/[^a-zA-Z0-9@]/g, '');
        },

        useDefaultId(schema) {
            schema.specialProps.id = this.suggestedId(schema);
        },

        suggestedId(schema) {
            if (!schema.specialProps.type) {
                return '';
            }
            return `{{ site:url }}/#${schema.specialProps.type}`;
        },

        handleTypeChange(field) {
            if (field.type === 'object') {
                field.value = {
                    specialProps: {
                        type: '',
                        id: ''
                    },
                    fields: []
                };
            } else if (field.type === 'array') {
                field.values = [];
            } else if (field.type === 'object_array') {
                field.values = [];
            } else if (field.type === 'data_object') {
                field.value = '';
            } else {
                field.value = '';
            }
        },

        togglePreview() {
            this.showPreview = !this.showPreview;
        },

        toggleSchema(index) {
            this.$set(this.collapsedSchemas, index, !this.collapsedSchemas[index]);
        },

        isSchemaCollapsed(index) {
            return this.collapsedSchemas[index] || false;
        },

        removeSchema(index) {
            this.schemas.splice(index, 1);
        },

        getFieldByInput(inputElement) {
            const fieldElement = inputElement.closest('.field');
            if (fieldElement) {
                const schemaIndex = fieldElement.dataset.schemaIndex;
                const fieldIndex = fieldElement.dataset.fieldIndex;
                return this.schemas[schemaIndex].fields[fieldIndex];
            }
            return null;
        },

        onEnd(event) {
            // This method will be called when dragging ends, and the fields will be updated automatically.
        },
    }
}
</script>

<style>
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
