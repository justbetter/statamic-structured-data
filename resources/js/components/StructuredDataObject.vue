<template>
    <div class="structured-data-object">
        <div class="mb-4">
            <div class="mb-2">
                <label class="block mb-1">@type</label>
                <input
                    type="text"
                    v-model="objectData.specialProps.type"
                    class="input-text"
                    placeholder="e.g. Organization, Product, BlogPosting"
                />
            </div>

            <div class="mb-2">
                <label class="block mb-1">@id</label>
                <div class="flex">
                    <input type="text" v-model="objectData.specialProps.id" class="input-text flex-1" :placeholder="suggestedId" />
                    <button class="btn ml-2" @click="useDefaultId">Use Default</button>
                </div>
            </div>
        </div>

        <div>
            <div v-for="(field, index) in objectData.fields" :key="index" class="mb-4 border-b pb-4">
                <div class="flex items-start gap-2">
                    <div class="flex-1">
                        <label class="block mb-1">Key</label>
                        <input
                            type="text"
                            v-model="field.key"
                            class="input-text"
                            @input="validateKey(field)"
                        />
                    </div>

                    <div class="w-32">
                        <label class="block mb-1">Type</label>
                        <v-select
                            v-model="field.type"
                            :options="selectOptions"
                            @input="(value) => { field.type = value.value; handleTypeChange(field); }"
                        />
                    </div>


                    <button class="btn-close mt-6" @click="removeField(index)">&times;</button>
                </div>

                <div class="mt-2">
                    <div v-if="field.type === 'string'">
                        <input type="text" v-model="field.value" class="input-text w-full" />
                    </div>

                    <div v-else-if="field.type === 'numeric'">
                        <input type="number" v-model="field.value" class="input-text w-full" />
                    </div>

                    <div v-else-if="field.type === 'array'" class="space-y-2">
                        <div v-for="(value, valueIndex) in field.values" :key="valueIndex" class="flex gap-2">
                            <input type="text" v-model="field.values[valueIndex]" class="input-text flex-1" />
                            <button class="btn-close" @click="removeArrayValue(field, valueIndex)">&times;</button>
                        </div>
                        <button class="btn" @click="addArrayValue(field)">Add Value</button>
                    </div>

                    <div v-else-if="field.type === 'object'" class="mt-2 border rounded p-4">
                        <structured-data-object
                            v-model="field.value"
                            :base-url="baseUrl"
                            :parent-type="objectData.specialProps.type"
                            :field-key="field.key"
                        />
                    </div>
                </div>
            </div>

            <button class="btn" @click="addField">Add Property</button>
        </div>
    </div>
</template>

<script>
export default {
    name: 'StructuredDataObject',

    props: {
        value: {
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
            objectData: JSON.parse(JSON.stringify(this.value))
        }
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
                { value: 'object', label: 'Object' }
            ];
        }
    },

    watch: {
        objectData: {
            deep: true,
            handler(val) {
                const newVal = JSON.stringify(val);
                const oldVal = JSON.stringify(this.value);
                if (newVal !== oldVal) {
                    this.$emit('input', JSON.parse(JSON.stringify(val)));
                }
            }
        },

        'objectData.specialProps.type'(newType) {
            if (!this.objectData.specialProps.id && newType) {
                this.useDefaultId();
            }
        },

        value: {
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
                fields: []
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
            } else {
                field.value = '';
            }
        }
    }
}
</script>

<style>
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
