<template>
    <div>
        <!-- Warning notice -->
        <div class="mb-6 flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-400/25 dark:bg-amber-300/6 dark:text-amber-200">
            <ui-icon name="alert-warning-exclamation-mark" class="size-4 shrink-0 mt-0.5" />
            <span>{{ __('Changes are saved directly to the related entries when you click Save.') }}</span>
        </div>

        <!-- Current related items -->
        <div class="mb-4">
            <div v-if="localItems.length === 0" class="rounded-lg border border-dashed border-gray-300 dark:border-gray-700 p-6 text-center text-gray-500">
                {{ __('No related items') }}
            </div>
            <template v-else>
                <template v-if="mode !== 'assets'">
                    <related-item
                        v-for="item in localItems"
                        :key="item.id"
                        :item="item"
                        :removable="true"
                        @remove="removeItem(item)"
                    />
                </template>
                <template v-else>
                    <related-asset
                        v-for="item in localItems"
                        :key="item.id"
                        :asset="item"
                        :removable="true"
                        @remove="removeItem(item)"
                    />
                </template>
            </template>
        </div>

        <!-- Link button -->
        <ui-button
            v-if="!showSearch"
            icon="link"
            size="sm"
            :text="linkLabel"
            @click="openSearch"
        />

        <!-- Search / selector panel -->
        <div v-if="showSearch" class="mt-2 rounded-lg border border-gray-300 dark:border-gray-700 overflow-hidden">
            <!-- Search header -->
            <div class="bg-gray-100 dark:bg-gray-800 px-4 py-2.5 border-b border-gray-300 dark:border-gray-700">
                <div class="relative">
                    <div class="absolute inset-y-0 start-0 flex items-center ps-3 pointer-events-none">
                        <ui-icon name="magnifying-glass" class="size-4 text-gray-400" />
                    </div>
                    <input
                        ref="searchInput"
                        v-model="searchQuery"
                        type="text"
                        class="input-text w-full ps-9"
                        :placeholder="__('Search...')"
                        @input="debouncedSearch"
                    />
                </div>
            </div>

            <!-- Results -->
            <div class="max-h-72 overflow-y-auto">
                <div v-if="searching" class="flex justify-center py-6">
                    <ui-icon name="loading" />
                </div>

                <div v-else-if="searchResults.length === 0 && searchQuery" class="py-6 text-center text-sm text-gray-500">
                    {{ __('No results found') }}
                </div>

                <div v-else-if="searchResults.length === 0 && !searchQuery" class="py-6 text-center text-sm text-gray-500">
                    {{ __('No items available') }}
                </div>

                <template v-else>
                    <div
                        v-for="result in searchResults"
                        :key="result.id"
                        class="flex cursor-pointer items-center gap-3 px-4 py-2 text-sm border-b last:border-b-0 border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-800/50"
                        :class="{ 'bg-blue-50 dark:bg-blue-900/20': selectedIds.includes(result.id) }"
                        @click="toggleSelection(result.id)"
                    >
                        <input
                            type="checkbox"
                            :checked="selectedIds.includes(result.id)"
                            class="form-checkbox"
                            @click.stop
                            @change="toggleSelection(result.id)"
                        />
                        <img v-if="result.thumbnail" :src="result.thumbnail" class="size-8 shrink-0 rounded object-cover" />
                        <ui-status-indicator v-if="result.status" :status="result.status" />
                        <span class="flex-1 line-clamp-1" v-text="result.title" />
                        <span v-if="result.hint || result.collection" class="text-2xs tracking-tight text-gray-500 whitespace-nowrap" v-text="result.hint || result.collection" />
                    </div>
                </template>
            </div>

            <!-- Footer -->
            <div class="bg-gray-100 dark:bg-gray-800 px-4 py-2.5 flex items-center justify-between border-t border-gray-300 dark:border-gray-700">
                <div class="text-xs text-gray-500">
                    <span v-if="selectedIds.length > 0">{{ selectedIds.length }} {{ __('selected') }}</span>
                </div>
                <div class="flex gap-2">
                    <ui-button variant="ghost" size="sm" :text="__('Cancel')" @click="cancelSearch" />
                    <ui-button variant="primary" size="sm" :text="__('Select')" :disabled="selectedIds.length === 0" @click="addSelected" />
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, nextTick, getCurrentInstance } from 'vue';
import RelatedItem from './RelatedItem.vue';
import RelatedAsset from './RelatedAsset.vue';

const props = defineProps({
    items: { type: Array, required: true },
    config: { type: String, required: true },
    parentId: { type: String, required: true },
    mode: { type: String, default: 'entries' },
});

const emit = defineEmits(['saved', 'cancel', 'saving']);

const { proxy } = getCurrentInstance();

const localItems = ref([...props.items]);
const showSearch = ref(false);
const searchQuery = ref('');
const searchResults = ref([]);
const selectedIds = ref([]);
const searching = ref(false);
const saving = ref(false);
const searchInput = ref(null);

let debounceTimer = null;

const linkLabel = computed(() => {
    if (props.mode === 'assets') return proxy.__('Link Asset');
    if (props.mode === 'terms') return proxy.__('Link Term');
    return proxy.__('Link Entry');
});

function removeItem(item) {
    localItems.value = localItems.value.filter(i => i.id !== item.id);
}

function toggleSelection(id) {
    const idx = selectedIds.value.indexOf(id);
    if (idx === -1) {
        selectedIds.value = [...selectedIds.value, id];
    } else {
        selectedIds.value = selectedIds.value.filter(i => i !== id);
    }
}

function openSearch() {
    showSearch.value = true;
    searchQuery.value = '';
    searchResults.value = [];
    selectedIds.value = [];
    nextTick(() => {
        searchInput.value?.focus();
        doSearch();
    });
}

function cancelSearch() {
    showSearch.value = false;
    searchQuery.value = '';
    searchResults.value = [];
    selectedIds.value = [];
}

function debouncedSearch() {
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(doSearch, 300);
}

function doSearch() {
    searching.value = true;
    const excludeIds = localItems.value.map(i => i.id).join(',');

    proxy.$axios.get(cp_url('reverse-relationship/search'), {
        params: {
            config: props.config,
            query: searchQuery.value,
            exclude: excludeIds,
        },
    }).then(response => {
        searchResults.value = response.data.data;
        searching.value = false;
    }).catch(() => {
        searching.value = false;
    });
}

function addSelected() {
    const newItems = searchResults.value.filter(r => selectedIds.value.includes(r.id));
    localItems.value = [...localItems.value, ...newItems];
    cancelSearch();
}

function save() {
    if (saving.value) return;
    saving.value = true;
    emit('saving', true);

    const relatedIds = localItems.value.map(i => i.id);

    proxy.$axios.post(cp_url('reverse-relationship/sync'), {
        id: props.parentId,
        config: props.config,
        related: relatedIds,
    }).then(response => {
        saving.value = false;
        emit('saving', false);
        if (response.data.errors && response.data.errors.length > 0) {
            Statamic.$toast.error(response.data.errors.join(', '));
        } else {
            Statamic.$toast.success(proxy.__('Saved'));
        }
        emit('saved', response.data.data);
    }).catch(() => {
        saving.value = false;
        emit('saving', false);
        Statamic.$toast.error(proxy.__('Save failed'));
    });
}

defineExpose({ save });
</script>
