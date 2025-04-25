<template>
    <div class="variables-panel">
        <div @click="toggleVariables" class="p-2 border-b flex justify-between items-center bg-gray-50 cursor-pointer">
            <h3 class="font-bold">{{ __('Available Variables') }}</h3>
            <div class="text-gray-500 hover:text-gray-700 rotate-180">
                <div :class="['chevron', { 'chevron-up': !variablesCollapsed }]"></div>
            </div>
        </div>
        <div v-if="!variablesCollapsed" class="py-4 bg-white">
            <div v-if="!hasVariables" class="text-gray-500 text-sm">
                {{ __('No variables available for this collection.') }}
            </div>
            <div v-else class="grid grid-cols-2">
                <div v-for="(variablesType, section) in variables" :key="variablesType">
                    <div v-if="variablesType.length" class="mb-4">
                        <h4 class="font-semibold mb-2 text-gray-700 capitalize" v-text="section"></h4>
                        <div
                            v-for="variable in variablesType" :key="variable.name"
                            class="variable-item p-1 rounded"
                        >
                            <div v-if="variable.children && variable.children.length">
                                <div class="mb-2 cursor-pointer flex justify-between items-center text-sm" @click="toggleChildren(variable.name)">
                                    <div>{{ variable.description }}</div>
                                    <div>{{ showChildren[variable.name] ? '▼' : '►' }}</div>
                                </div>
                                <div v-if="showChildren[variable.name]">
                                    <div
                                        v-for="childVariable in variable.children" :key="childVariable.name"
                                        class="variable-item p-1 hover:bg-gray-100 cursor-pointer rounded"
                                        @click="copyVariable('{{ ' + childVariable.name + ' }}', $event)"
                                    >
                                        <div class="text-sm">{{ childVariable.description }}</div>
                                        <div class="text-xs text-gray-500">{{ childVariable.name }}</div>
                                    </div>
                                </div>
                            </div>
                            <div v-else @click="copyVariable('{{ ' + variable.name + ' }}', $event)" class="cursor-pointer hover:bg-gray-100">
                                <div class="text-sm">{{ variable.description }}</div>
                                <div class="text-xs text-gray-500">{{ variable.name }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div v-show="tooltipVisible" class="tooltip" :style="tooltipStyle">Copied!</div>
    </div>
</template>

<script>
export default {
    mixins: [Fieldtype],

    data() {
        return {
            showChildren: {},
            variablesCollapsed: false,
            tooltipVisible: false,
            tooltipStyle: {
                position: 'absolute',
                top: '0',
                left: '0',
            }
        }
    },

    computed: {
        variables() {
            return this.meta?.variables || {
                config: {},
                entry: []
            };
        },

        hasVariables() {
            return Object.keys(this.variables).length > 0;
        }
    },

    methods: {
        toggleVariables() {
            this.variablesCollapsed = !this.variablesCollapsed;
        },

        toggleChildren(variableName) {
            this.$set(this.showChildren, variableName, !this.showChildren[variableName]);
        },

        copyVariable(variable, event) {
            if (navigator.clipboard) {
                navigator.clipboard.writeText(variable).then(() => {
                    console.log('Variable copied to clipboard:', variable);
                    this.tooltipVisible = true;
                    this.tooltipStyle.top = `${event.layerY}px`;
                    this.tooltipStyle.left = `${event.layerX}px`;
                    setTimeout(() => {
                        this.tooltipVisible = false;
                    }, 2000);
                }).catch(err => {
                    console.error('Could not copy text: ', err);
                });
            } else {
                console.warn('Clipboard API not supported or not running in a secure context.');
            }
        }
    }
}
</script>

<style scoped>
.variables-panel {
    position: relative;
    transition: height 0.2s ease-in-out;
}
.variable-item {
    transition: background-color 0.2s ease-in-out;
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
