<template>
    <div 
        v-if="visible" 
        class="fixed inset-0 bg-gray-900 bg-opacity-75 flex justify-center items-center p-4" 
        style="z-index: 9999;"
        @click.self="close"
    >
        <div class="bg-white dark:bg-gray-800 rounded-lg w-full max-w-xl flex flex-col shadow-2xl" style="max-height: 60vh;">
            <div class="flex justify-between items-center px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex-shrink-0">
                <h3 class="font-bold text-lg text-gray-900 dark:text-white">{{ __('Add Preset') }}</h3>
                <button 
                    @click="close" 
                    class="text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300 text-2xl leading-none bg-transparent border-0 cursor-pointer transition-colors"
                >
                    &times;
                </button>
            </div>

            <div class="px-6 py-4 overflow-y-auto flex-1 min-h-0">
                <div v-if="!selectedPreset" class="preset-selection">
                    <p class="text-gray-600 dark:text-gray-300 mb-4">{{ __('Choose a preset to add to your schema:') }}</p>
                    
                    <div class="grid grid-cols-1 gap-3">
                        <div 
                            v-for="preset in presets" 
                            :key="preset.name"
                            @click="selectPreset(preset)"
                            class="border border-gray-200 dark:border-gray-700 rounded-md p-3 cursor-pointer transition-all duration-200 hover:border-blue-500 hover:shadow-sm hover:shadow-blue-500/10 dark:hover:border-blue-400 bg-white dark:bg-gray-800"
                        >
                            <div class="flex justify-between items-start">
                                <div class="flex-1">
                                    <h4 class="font-semibold text-gray-900 dark:text-white text-sm">{{ preset.name }}</h4>
                                    <p class="text-xs text-gray-600 dark:text-gray-300 mt-1">{{ preset.description }}</p>
                                    
                                    <div class="mt-2">
                                        <div class="text-xs text-gray-500 dark:text-gray-400 mb-1">{{ __('Fields:') }}</div>
                                        <div class="flex flex-wrap gap-1">
                                            <span 
                                                v-for="field in preset.schema.fields.slice(0, 3)" 
                                                :key="field.key"
                                                class="bg-blue-50 dark:bg-blue-900/30 text-blue-800 dark:text-blue-300 px-1.5 py-0.5 rounded text-[9px] font-medium"
                                            >
                                                {{ field.key }}
                                            </span>
                                            <span 
                                                v-if="preset.schema.fields.length > 3"
                                                class="bg-gray-100 dark:bg-gray-600 text-gray-600 dark:text-gray-300 px-1.5 py-0.5 rounded text-[9px]"
                                            >
                                                +{{ preset.schema.fields.length - 3 }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="bg-gray-100 dark:bg-gray-600 text-gray-700 dark:text-gray-200 px-2 py-1 rounded text-xs font-medium ml-2">
                                    {{ preset.schema.specialProps.type }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else class="preset-actions">
                    <div class="mb-4 p-3 bg-blue-50 dark:bg-blue-900/20 rounded border border-blue-200 dark:border-blue-800">
                        <h4 class="font-semibold text-blue-800 dark:text-blue-200">{{ selectedPreset.name }}</h4>
                        <p class="text-sm text-blue-600 dark:text-blue-300">{{ selectedPreset.description }}</p>
                    </div>

                    <div v-if="hasExistingSchemas" class="action-selection">
                        <p class="text-gray-700 dark:text-gray-200 mb-4">{{ __('You have existing schemas. How would you like to add this preset?') }}</p>
                        
                        <div class="flex flex-col gap-3">
                            <button 
                                @click="handleAction('merge')"
                                class="border border-gray-200 dark:border-gray-700 rounded-md p-4 text-left cursor-pointer transition-all duration-200 bg-white dark:bg-gray-800 w-full hover:border-green-500 hover:shadow-md hover:shadow-green-500/10 dark:hover:border-green-400"
                            >
                                <div class="flex flex-col gap-1">
                                    <div class="font-semibold text-gray-700 dark:text-gray-200">{{ __('Merge (Recommended)') }}</div>
                                    <div class="text-sm text-gray-600 dark:text-gray-300">{{ __('Add this preset as an additional schema alongside your existing ones') }}</div>
                                </div>
                            </button>
                            
                            <button 
                                @click="handleAction('override')"
                                class="border border-gray-200 dark:border-gray-700 rounded-md p-4 text-left cursor-pointer transition-all duration-200 bg-white dark:bg-gray-800 w-full hover:border-amber-500 hover:shadow-md hover:shadow-amber-500/10 dark:hover:border-amber-400"
                            >
                                <div class="flex flex-col gap-1">
                                    <div class="font-semibold text-gray-700 dark:text-gray-200">{{ __('Override') }}</div>
                                    <div class="text-sm text-gray-600 dark:text-gray-300">{{ __('Replace all existing schemas with this preset') }}</div>
                                </div>
                            </button>
                        </div>
                    </div>
                    
                    <div v-else class="no-existing-schemas">
                        <p class="text-gray-600 dark:text-gray-300 mb-4">{{ __('This preset will be added as your first schema.') }}</p>
                        <button 
                            @click="handleAction('add')"
                            class="px-6 py-3 bg-blue-600 hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600 text-white border-0 rounded cursor-pointer font-medium transition-all duration-200 w-full"
                        >
                            {{ __('Add Preset') }}
                        </button>
                    </div>
                </div>
            </div>

            <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex justify-end flex-shrink-0">
                <button 
                    @click="goBack" 
                    v-if="selectedPreset" 
                    class="mr-2 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 cursor-pointer transition-all duration-200 hover:bg-gray-50 dark:hover:bg-gray-600 text-sm"
                >
                    {{ __('Back') }}
                </button>
                <button 
                    @click="close" 
                    class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 cursor-pointer transition-all duration-200 hover:bg-gray-50 dark:hover:bg-gray-600 text-sm"
                >
                    {{ __('Cancel') }}
                </button>
            </div>
        </div>
    </div>
</template>

<script>
export default {
    name: 'PresetModal',

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

    watch: {
        visible: {
            immediate: true,
            handler(newVal) {
                if (newVal) {
                    this.lockBodyScroll();
                } else {
                    this.unlockBodyScroll();
                    this.selectedPreset = null;
                }
            }
        }
    },

    beforeDestroy() {
        this.unlockBodyScroll();
    },

    methods: {
        lockBodyScroll() {
            document.body.style.overflow = 'hidden';
        },

        unlockBodyScroll() {
            document.body.style.overflow = '';
        },

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