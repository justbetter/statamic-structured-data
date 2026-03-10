<template>
    <Stack
        v-model:open="isOpen"
        :title="__('Add Preset')"
    >
        <div class="max-h-[60vh] flex flex-col gap-4">
            <div
                v-if="!selectedPreset"
                class="preset-selection space-y-4"
            >
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    {{ __('Choose a preset to add to your schema:') }}
                </p>

                <div class="grid grid-cols-1 gap-3">
                    <Card
                        v-for="preset in presets"
                        :key="preset.name"
                        class="cursor-pointer hover:border-blue-500 hover:shadow-sm hover:shadow-blue-500/10"
                        @click="selectPreset(preset)"
                    >
                        <div class="flex justify-between items-start gap-3">
                            <div class="flex-1">
                                <h4 class="font-semibold text-gray-900 dark:text-white text-sm">
                                    {{ preset.name }}
                                </h4>
                                <p class="text-xs text-gray-600 dark:text-gray-300 mt-1">
                                    {{ preset.description }}
                                </p>

                                <div class="mt-2">
                                    <div class="text-[11px] text-gray-500 dark:text-gray-400 mb-1">
                                        {{ __('Fields:') }}
                                    </div>
                                    <div class="flex flex-wrap gap-1">
                                        <Badge
                                            v-for="field in preset.schema.fields.slice(0, 3)"
                                            :key="field.key"
                                            size="xs"
                                            class="bg-blue-50 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300"
                                        >
                                            {{ field.key }}
                                        </Badge>
                                        <Badge
                                            v-if="preset.schema.fields.length > 3"
                                            size="xs"
                                            class="bg-gray-100 dark:bg-gray-600 text-gray-600 dark:text-gray-300"
                                        >
                                            +{{ preset.schema.fields.length - 3 }}
                                        </Badge>
                                    </div>
                                </div>
                            </div>
                            <Badge
                                size="xs"
                                class="bg-gray-100 dark:bg-gray-600 text-gray-700 dark:text-gray-200"
                            >
                                {{ preset.schema.specialProps.type }}
                            </Badge>
                        </div>
                    </Card>
                </div>
            </div>

            <div
                v-else
                class="preset-actions space-y-4"
            >
                <Card class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800">
                    <h4 class="font-semibold text-blue-800 dark:text-blue-200">
                        {{ selectedPreset.name }}
                    </h4>
                    <p class="text-sm text-blue-600 dark:text-blue-300 mt-1">
                        {{ selectedPreset.description }}
                    </p>
                </Card>

                <div
                    v-if="hasExistingSchemas"
                    class="action-selection space-y-3"
                >
                    <p class="text-sm text-gray-700 dark:text-gray-200">
                        {{ __('You have existing schemas. How would you like to add this preset?') }}
                    </p>

                    <Card
                        class="cursor-pointer hover:border-green-500 hover:shadow-md hover:shadow-green-500/10 dark:hover:border-green-400"
                        @click="handleAction('merge')"
                    >
                        <div class="flex flex-col gap-1">
                            <div class="font-semibold text-gray-700 dark:text-gray-200">
                                {{ __('Merge (Recommended)') }}
                            </div>
                            <div class="text-sm text-gray-600 dark:text-gray-300">
                                {{ __('Add this preset as an additional schema alongside your existing ones') }}
                            </div>
                        </div>
                    </Card>

                    <Card
                        class="cursor-pointer hover:border-amber-500 hover:shadow-md hover:shadow-amber-500/10 dark:hover:border-amber-400"
                        @click="handleAction('override')"
                    >
                        <div class="flex flex-col gap-1">
                            <div class="font-semibold text-gray-700 dark:text-gray-200">
                                {{ __('Override') }}
                            </div>
                            <div class="text-sm text-gray-600 dark:text-gray-300">
                                {{ __('Replace all existing schemas with this preset') }}
                            </div>
                        </div>
                    </Card>
                </div>

                <div
                    v-else
                    class="no-existing-schemas space-y-3"
                >
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        {{ __('This preset will be added as your first schema.') }}
                    </p>
                    <Button
                        class="w-full"
                        @click="handleAction('add')"
                    >
                        {{ __('Add Preset') }}
                    </Button>
                </div>
            </div>
        </div>

        <template #footer>
            <div class="flex justify-end gap-2">
                <Button
                    v-if="selectedPreset"
                    variant="ghost"
                    size="sm"
                    @click="goBack"
                >
                    {{ __('Back') }}
                </Button>
                <Button
                    variant="subtle"
                    size="sm"
                    @click="close"
                >
                    {{ __('Cancel') }}
                </Button>
            </div>
        </template>
    </Stack>
</template>

<script>
import { Badge, Button, Card, Stack } from '@statamic/cms/ui';

export default {
    name: 'PresetStack',

    components: {
        Badge,
        Button,
        Card,
        Stack,
    },

    props: {
        visible: {
            type: Boolean,
            default: false
        },
        presets: {
            type: Array,
            default: () => []
        },
        hasExistingSchemas: {
            type: Boolean,
            default: false
        }
    },

    data() {
        return {
            selectedPreset: null
        }
    },

    computed: {
        isOpen: {
            get() {
                return this.visible;
            },
            set(value) {
                if (!value) {
                    this.close();
                }
            },
        },
    },

    methods: {
        close() {
            this.selectedPreset = null;
            this.$emit('close');
        },

        selectPreset(preset) {
            this.selectedPreset = preset;
        },

        goBack() {
            this.selectedPreset = null;
        },

        handleAction(action) {
            this.$emit('preset-selected', {
                preset: this.selectedPreset,
                action: action
            });
            this.close();
        }
    }
}
</script>