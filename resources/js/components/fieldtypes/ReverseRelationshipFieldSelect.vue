<template>
    <div>
        <input
            ref="inputEl"
            type="text"
            :value="value"
            @input="$emit('update:value', $event.target.value)"
            class="input-text border border-gray-400 dark:border-gray-700 rounded-sm"
            :placeholder="__('Type a field handle...')"
        />

        <div v-if="loading" class="help-block mt-2">
            <span class="text-gray-500 text-xs">{{ __('Loading fields...') }}</span>
        </div>

        <div v-else-if="options.length > 0" class="mt-2">
            <span class="text-gray-500 text-xs block mb-1">{{ __('Relationship fields found:') }}</span>
            <div class="flex flex-wrap gap-1">
                <button
                    v-for="opt in options"
                    :key="opt.handle"
                    type="button"
                    class="text-xs px-2 py-0.5 rounded-sm border cursor-pointer"
                    :class="value === opt.handle
                        ? 'bg-blue-100 border-blue-300 text-blue-800 dark:bg-blue-900/50 dark:border-blue-700 dark:text-blue-200'
                        : 'bg-gray-100 border-gray-200 text-gray-600 hover:bg-gray-200 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-700'"
                    @click="selectOption(opt.handle)"
                >
                    {{ opt.display }} <span class="opacity-50">{{ opt.handle }}</span>
                </button>
            </div>
        </div>

        <div v-else-if="!loading && attempted && options.length === 0 && resourceHandle" class="help-block mt-2">
            <span class="text-gray-500 text-xs">{{ __('No relationship fields found on this resource.') }}</span>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, getCurrentInstance, nextTick, inject } from 'vue';

const { proxy } = getCurrentInstance();

const props = defineProps({
    value: { default: '' },
    config: { type: Object, default: () => ({}) },
    handle: { type: String, default: '' },
    meta: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['update:value']);

const inputEl = ref(null);
const loading = ref(false);
const options = ref([]);
const attempted = ref(false);
const resourceHandle = ref(null);

// Track last-seen sibling values to detect changes
let lastMode = null;
let lastHandle = null;
let pollInterval = null;

// Statamic's Settings.vue provides this to read sibling config field values
const getFieldSettingsValue = inject('getFieldSettingsValue', null);

function selectOption(handle) {
    emit('update:value', handle);

    // Also force-update the native input element so the user sees the change immediately
    if (inputEl.value) {
        inputEl.value.value = handle;
    }
}

function getResourceInfo() {
    if (!getFieldSettingsValue) return null;

    const mode = getFieldSettingsValue('mode') || 'entries';
    let handle = null;

    if (mode === 'entries') handle = getFieldSettingsValue('collection');
    else if (mode === 'terms') handle = getFieldSettingsValue('taxonomy');
    else if (mode === 'assets') handle = getFieldSettingsValue('container');

    // Statamic may store these as arrays
    if (Array.isArray(handle)) handle = handle[0];

    return handle ? { mode, handle } : null;
}

async function fetchOptions(mode, handle) {
    if (!mode || !handle) return;

    loading.value = true;

    try {
        const response = await proxy.$axios.get(cp_url('reverse-relationship/fields'), {
            params: { mode, handle },
        });
        options.value = response.data.data || [];

        // Auto-select when there's exactly one option and no value is set yet
        if (options.value.length === 1 && !props.value) {
            selectOption(options.value[0].handle);
        }
    } catch {
        options.value = [];
    } finally {
        loading.value = false;
        attempted.value = true;
    }
}

function checkForChanges() {
    const info = getResourceInfo();
    const newMode = info?.mode || null;
    const newHandle = info?.handle || null;

    if (newMode !== lastMode || newHandle !== lastHandle) {
        lastMode = newMode;
        lastHandle = newHandle;
        resourceHandle.value = newHandle;

        if (newMode && newHandle) {
            fetchOptions(newMode, newHandle);
        } else {
            options.value = [];
            attempted.value = false;
        }
    }
}

onMounted(async () => {
    // Wait for Settings.vue to finish loading config values
    await nextTick();
    await nextTick();

    const info = getResourceInfo();
    lastMode = info?.mode || null;
    lastHandle = info?.handle || null;

    if (info) {
        resourceHandle.value = info.handle;
        fetchOptions(info.mode, info.handle);
    }

    // Poll for sibling config changes (mode, collection, taxonomy, container)
    pollInterval = setInterval(checkForChanges, 500);
});

onBeforeUnmount(() => {
    if (pollInterval) {
        clearInterval(pollInterval);
    }
});
</script>
