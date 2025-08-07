<template>
    <div class="structured-data-builder">
        <div class="flex-1">
            <div v-if="schema" class="structured-data-schema mb-6 rounded-lg shadow-sm border">
                <div class="px-4 py-2 bg-gray-50 border-b rounded-t-lg flex justify-between items-center">
                    <div class="flex items-center gap-2 cursor-pointer">
                        <h3 class="font-bold text-lg">{{ __('Object Data Builder') }}</h3>
                    </div>
                </div>
                <div class="p-4">
                    <div class="space-y-3">
                        <h4 class="text-gray-600 mb-2">{{ __('Fields') }}</h4>
                        <div v-for="(field, index) in schema.fields" :key="index" class="mb-2 border rounded bg-gray-50">
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
                                    <input
                                        v-if="field.type === 'numeric'"
                                        type="number"
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
                                            {{ __('Add Value') }}
                                        </button>
                                    </div>
                                    <div v-else-if="field.type === 'object'" class="mt-2">
                                        <structured-data-object
                                            v-model="field.value"
                                            :base-url="baseUrl"
                                            :field-key="field.key"
                                        />
                                    </div>
                                </div>
                                <div class="flex justify-end mt-3">
                                    <button
                                        @click="removeField(index)"
                                        class="btn-danger"
                                    >
                                        {{ __('Remove Field') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                        <button @click="addField()" class="btn-primary mt-2">
                            {{ __('Add Field') }}
                        </button>
                    </div>
                </div>
            </div>

            <div class="flex gap-2 mt-4">
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

export default {
    name: 'StructuredDataObjectBuilder',
    mixins: [Fieldtype],

    components: {
        'structured-data-object': StructuredDataObject
    },

    props: {
        value: {
            type: Object,
            default: () => ({
                fields: []
            })
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
            schema: this.value || {
                fields: []
            },
            showPreview: false,
            collapsedSchemas: {}
        }
    },

    computed: {
        baseUrl() {
            return this.config?.base_url || '';
        },

        preview() {
            return formatSchemaJson(this.schema);
        },

        selectOptions() {
            return [
                { value: 'string', label: 'String' },
                { value: 'numeric', label: 'Numeric' },
                { value: 'array', label: 'Array' },
                { value: 'object', label: 'Object' }
            ];
        }
    },

    watch: {
        schema: {
            deep: true,
            handler(val) {
                this.$emit('input', val);
            }
        }
    },

    methods: {
        addField() {
            this.schema.fields.push({
                key: '',
                type: 'string',
                value: '',
                values: [],
                fields: []
            });
        },

        removeField(index) {
            this.schema.fields.splice(index, 1);
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
            } else {
                field.value = '';
            }
        },

        togglePreview() {
            this.showPreview = !this.showPreview;
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
