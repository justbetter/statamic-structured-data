<template>
    <Card class="variables-panel p-0!">
        <div
            class="flex items-center justify-between px-3 py-2 border-b cursor-pointer"
            :class="{ 'border-b-0': variablesCollapsed }"
            @click="toggleVariables"
        >
            <div class="flex items-center gap-2">
                <h3 class="text-xs font-semibold uppercase tracking-wide">
                    {{ __('Available Variables') }}
                </h3>
            </div>
            <Button
                variant="ghost"
                size="xs"
                class="flex items-center gap-1 text-xs"
                :aria-expanded="!variablesCollapsed"
            >
                <div :class="['chevron', { 'chevron-up': !variablesCollapsed }]"></div>
            </Button>
        </div>

        <div
            v-if="!variablesCollapsed"
            class="structured-data-variables-list max-h-80 overflow-y-auto px-3 py-3"
        >
            <div
                v-if="!hasVariables"
                class="text-xs"
            >
                {{ __('No variables available for this collection.') }}
            </div>
            <div
                v-else
                class="grid grid-cols-1 gap-3"
            >
                <div
                    v-for="(variablesType, section) in variables"
                    :key="section"
                >
                    <div v-if="variablesType.length">
                        <Label
                            class="mb-1.5 text-[0.65rem] font-semibold uppercase tracking-wide"
                            :text="section"
                        />

                        <div
                            v-for="variable in variablesType"
                            :key="variable.name"
                            class="mb-1"
                        >
                            <div v-if="variable.children && variable.children.length">
                                <Label
                                    @click.stop="toggleChildren(variable.name)"
                                    class="cursor-pointer"
                                >
                                    <div class="flex flex-col justify-between text-left">
                                        <span class="font-bold">
                                            {{ variable.description }}
                                        </span>
                                        <span class="font-medium text-xs">
                                            {{ showChildren[variable.name] ? '▼' : '►' }}
                                        </span>
                                    </div>
                                </Label>

                                <div
                                    v-if="showChildren[variable.name]"
                                    class="mt-1 space-y-1"
                                >
                                    <Label
                                        v-for="childVariable in variable.children"
                                        :key="childVariable.name"
                                        @click.stop="copyVariable('{{ ' + childVariable.name + ' }}', $event)"
                                        class="cursor-pointer"
                                    >
                                        <div class="flex flex-col items-start">
                                            <span class="font-bold">
                                                {{ childVariable.description }}
                                            </span>
                                            <span class="font-medium">
                                                {{ childVariable.name }}
                                            </span>
                                        </div>
                                    </Label>
                                </div>
                            </div>
                            <div v-else>
                                <Label
                                    @click.stop="copyVariable('{{ ' + variable.name + ' }}', $event)"
                                    class="cursor-pointer"
                                >
                                    <div class="flex flex-col items-start">
                                        <span class="font-bold">
                                            {{ variable.description }}
                                        </span>
                                        <span class="font-normal">
                                            {{ variable.name }}
                                        </span>
                                    </div>
                                </Label>
                            </div>
                        </div>
                        <Separator />
                    </div>
                </div>
            </div>
        </div>

        <div
            v-show="tooltipVisible"
            class="tooltip"
            :style="tooltipStyle"
        >
            {{ __('Copied!') }}
        </div>
    </Card>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import { Fieldtype } from '@statamic/cms';
import { Button, Card, Label, Separator } from '@statamic/cms/ui';

const fieldtypeProps = defineProps(Fieldtype.props);
const { meta } = fieldtypeProps;

const emit = defineEmits(Fieldtype.emits);
const { expose } = Fieldtype.use(emit, fieldtypeProps);

defineExpose(expose);

const showChildren = reactive({});
const variablesCollapsed = ref(false);
const tooltipVisible = ref(false);
const tooltipStyle = reactive({
    position: 'absolute',
    top: '0',
    left: '0',
});

const variables = computed(() => {
    if (!meta || !meta.variables) {
        return {
            config: {},
            entry: [],
            term: [],
        };
    }

    return meta.variables;
});

const hasVariables = computed(() => {
    return Object.keys(variables.value).length > 0;
});

const toggleVariables = () => {
    variablesCollapsed.value = !variablesCollapsed.value;
};

const toggleChildren = variableName => {
    const currentState = showChildren[variableName] === true;

    showChildren[variableName] = !currentState;
};

const copyVariable = (variable, event) => {
    if (!navigator.clipboard) {
        // eslint-disable-next-line no-console
        console.warn('Clipboard API not supported or not running in a secure context.');

        return;
    }

    navigator.clipboard
        .writeText(variable)
        .then(() => {
            // eslint-disable-next-line no-console
            console.log('Variable copied to clipboard:', variable);
            tooltipVisible.value = true;
            tooltipStyle.top = `${event.layerY}px`;
            tooltipStyle.left = `${event.layerX}px`;

            window.setTimeout(() => {
                tooltipVisible.value = false;
            }, 2000);
        })
        .catch(copyError => {
            // eslint-disable-next-line no-console
            console.error('Could not copy text: ', copyError);
        });
};
</script>

<style scoped>
.variables-panel {
    position: relative;
    transition: height 0.2s ease-in-out;
}
.variable-item {
    transition: background-color 0.15s ease-in-out, color 0.15s ease-in-out;
}
.chevron {
    width: 10px;
    height: 10px;
    border-right: 2px solid currentColor;
    border-bottom: 2px solid currentColor;
    transform: rotate(45deg);
    transition: transform 0.2s ease-in-out;
}
.chevron-up {
    transform: rotate(-135deg);
}

.tooltip {
  position: absolute;
  background-color: rgba(0, 0, 0, 0.75);
  color: #fff;
  padding: 5px;
  border-radius: 4px;
  z-index: 100;
}
</style>
