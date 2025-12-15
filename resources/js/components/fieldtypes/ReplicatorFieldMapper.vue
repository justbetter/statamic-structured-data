<template>
    <div class="replicator-mapper space-y-3">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div>
                <label class="text-gray-600 mb-1 block">Replicator Field Handle</label>
                <input
                    type="text"
                    v-model="localConfig.replicator_field"
                    class="input-text w-full"
                    placeholder="e.g. features"
                />
            </div>
            <div>
                <label class="text-gray-600 mb-1 block">Limit to Set (optional)</label>
                <input
                    type="text"
                    v-model="localConfig.set"
                    class="input-text w-full"
                    placeholder="e.g. feature_item"
                />
            </div>
        </div>

        <div class="border rounded p-3">
            <div class="flex items-center justify-between mb-2">
                <h4 class="font-semibold text-gray-700">Field Mappings</h4>
                <button class="btn-primary text-sm" @click="addMapping">Add Mapping</button>
            </div>

            <div v-if="!localConfig.mappings.length" class="text-sm text-gray-500">
                No mappings yet. Add one to map replicator fields into your JSON-LD object.
            </div>

            <div
                v-for="(mapping, index) in localConfig.mappings"
                :key="index"
                class="border rounded mb-3 p-3"
            >
                <div class="flex items-start gap-2">
                    <div class="flex-1">
                        <label class="text-gray-600 mb-1 block">JSON-LD Key</label>
                        <input
                            type="text"
                            v-model="mapping.key"
                            class="input-text w-full"
                            placeholder="e.g. name"
                            @input="sanitizeKey(mapping)"
                        />
                    </div>
                    <div class="w-40">
                        <label class="text-gray-600 mb-1 block">Source</label>
                        <v-select
                            v-model="mapping.mode"
                            :options="modeOptions"
                            @input="(val) => { mapping.mode = val.value; }"
                        />
                    </div>
                    <button class="btn-danger mt-6" @click="removeMapping(index)">Remove</button>
                </div>

                <div class="mt-2">
                    <template v-if="mapping.mode === 'static'">
                        <label class="text-gray-600 mb-1 block">Static Value</label>
                        <input
                            type="text"
                            v-model="mapping.static"
                            class="input-text w-full"
                            placeholder="e.g. PropertyValue"
                        />
                    </template>

                    <template v-else-if="mapping.mode === 'field'">
                        <label class="text-gray-600 mb-1 block">Replicator Field Handle</label>
                        <input
                            type="text"
                            v-model="mapping.field"
                            class="input-text w-full"
                            placeholder="e.g. title"
                        />
                    </template>

                    <template v-else-if="mapping.mode === 'nested_replicator'">
                        <div class="p-3 border rounded">
                            <replicator-field-mapper
                                v-model="mapping.nested"
                            />
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'ReplicatorFieldMapper',
    props: {
        value: {
            type: Object,
            default: () => ({
                replicator_field: '',
                set: '',
                mappings: []
            })
        }
    },
    data() {
        return {
            localConfig: this.normalizeConfig(this.value),
            modeOptions: [
                { value: 'field', label: 'From Replicator Field' },
                { value: 'static', label: 'Static Value' },
                { value: 'nested_replicator', label: 'Nested Replicator' },
            ],
        }
    },
    watch: {
        localConfig: {
            deep: true,
            handler(val) {
                const newVal = JSON.stringify(val);
                const oldVal = JSON.stringify(this.value);

                if (newVal !== oldVal) {
                    this.$emit('input', JSON.parse(newVal));
                }
            }
        },
        value: {
            deep: true,
            handler(val) {
                const newVal = JSON.stringify(this.normalizeConfig(val));
                const oldVal = JSON.stringify(this.localConfig);

                if (newVal !== oldVal) {
                    this.localConfig = JSON.parse(newVal);
                }
            }
        }
    },
    methods: {
        normalizeConfig(config) {
            const base = {
                replicator_field: '',
                set: '',
                mappings: []
            };

            if (!config || typeof config !== 'object') {
                return base;
            }

            return {
                replicator_field: config.replicator_field || '',
                set: config.set || '',
                mappings: Array.isArray(config.mappings)
                    ? JSON.parse(JSON.stringify(config.mappings))
                    : []
            };
        },
        addMapping() {
            this.localConfig.mappings.push({
                key: '',
                mode: 'field',
                field: '',
                static: '',
                nested: {
                    replicator_field: '',
                    set: '',
                    mappings: []
                }
            });
        },
        removeMapping(index) {
            this.localConfig.mappings.splice(index, 1);
        },
        sanitizeKey(mapping) {
            mapping.key = mapping.key.replace(/[^a-zA-Z0-9@]/g, '');
        }
    }
}
</script>

