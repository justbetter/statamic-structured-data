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
                <p class="text-sm">
                    {{ __('Choose a preset to add to your schema:') }}
                </p>

                <div class="grid grid-cols-1 gap-3">
                    <Card
                        v-for="preset in presets"
                        :key="preset.name"
                        class="cursor-pointer"
                        @click="selectPreset(preset)"
                    >
                        <div class="flex justify-between items-start gap-3">
                            <div class="flex-1">
                                <h4 class="font-semibold text-sm">
                                    {{ preset.name }}
                                </h4>
                                <p class="text-xs mt-1">
                                    {{ preset.description }}
                                </p>

                                <div class="mt-2">
                                    <div class="text-[11px] mb-1">
                                        {{ __('Fields:') }}
                                    </div>
                                    <div class="flex flex-wrap gap-1">
                                        <Badge
                                            v-for="field in preset.schema.fields.slice(0, 3)"
                                            :key="field.key"
                                            size="xs"
                                            pill
                                        >
                                            <span class="py-1 px-2">
                                                {{ field.key }}
                                            </span>
                                        </Badge>
                                        <Badge
                                            v-if="preset.schema.fields.length > 3"
                                            size="xs"
                                            pill
                                        >
                                            <span class="py-1 px-2">
                                                +{{ preset.schema.fields.length - 3 }}
                                            </span>
                                        </Badge>
                                    </div>
                                </div>
                            </div>
                            <Badge size="xs" pill>
                                <span class="py-1 px-2">
                                    {{ preset.schema.specialProps.type }}
                                </span>
                            </Badge>
                        </div>
                    </Card>
                </div>
            </div>

                <div
                    v-else
                    class="preset-actions space-y-4"
                >
                    <Card>
                    <h4 class="font-semibold">
                        {{ selectedPreset.name }}
                    </h4>
                    <p class="text-sm mt-1">
                        {{ selectedPreset.description }}
                    </p>
                </Card>

                <div
                    v-if="hasExistingSchemas"
                    class="action-selection space-y-3"
                >
                    <p class="text-sm">
                        {{ __('You have existing schemas. How would you like to add this preset?') }}
                    </p>

                    <Card
                        class="cursor-pointer"
                        @click="handleAction('merge')"
                    >
                        <div class="flex flex-col gap-1">
                            <div class="font-semibold">
                                {{ __('Merge (Recommended)') }}
                            </div>
                            <div class="text-sm">
                                {{ __('Add this preset as an additional schema alongside your existing ones') }}
                            </div>
                        </div>
                    </Card>

                    <Card
                        class="cursor-pointer"
                        @click="handleAction('override')"
                    >
                        <div class="flex flex-col gap-1">
                            <div class="font-semibold">
                                {{ __('Override') }}
                            </div>
                            <div class="text-sm">
                                {{ __('Replace all existing schemas with this preset') }}
                            </div>
                        </div>
                    </Card>
                </div>

                <div
                    v-else
                    class="no-existing-schemas space-y-3"
                >
                    <p class="text-sm">
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

    emits: ['close', 'preset-selected'],

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