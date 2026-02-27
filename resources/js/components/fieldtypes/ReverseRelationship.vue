<template>
    <div>
        <div v-if="loading" class="p-4 flex justify-center">
            <ui-icon name="loading" />
        </div>

        <div
            v-else-if="items.length === 0 && !meta.editable"
            class="rounded-lg border border-dashed border-gray-300 dark:border-gray-700 p-6 text-center text-gray-500"
            v-text="__('No Related Items')"
        />

        <div v-else class="@container outline-none">
            <template v-if="config.mode !== 'assets'">
                <related-item
                    v-for="item in items"
                    :key="item.id"
                    :item="item"
                />
            </template>
            <template v-else>
                <related-asset
                    v-for="item in items"
                    :key="item.id"
                    :asset="item"
                />
            </template>

            <div v-if="items.length === 0 && meta.editable" class="rounded-lg border border-dashed border-gray-300 dark:border-gray-700 p-6 text-center text-gray-500">
                {{ __('No Related Items') }}
            </div>
        </div>

        <ui-button
            v-if="meta.editable && !loading"
            icon="pencil"
            size="sm"
            variant="ghost"
            :text="__('Edit')"
            class="mt-2"
            @click="isEditing = true"
        />

        <teleport to="#statamic">
            <transition name="stack-overlay-fade">
                <div v-if="isEditing" class="fixed inset-0 bg-gray-800/20 dark:bg-gray-800/50" style="z-index: 999" @click="isEditing = false" />
            </transition>
            <transition name="stack-slide">
                <div v-if="isEditing" class="fixed inset-y-2 sm:end-1.5 flex flex-col bg-white dark:bg-gray-900 rounded-xl shadow-lg overflow-hidden" :style="{ left: (windowWidth - 450) + 'px', zIndex: 1000 }">
                    <header class="bg-gray-200 dark:bg-gray-800 px-6 py-3 flex items-center justify-between border-b dark:border-gray-700">
                        <h2 class="text-lg font-medium" v-text="__('Edit Related Items')" />
                        <ui-button icon="x" variant="ghost" size="sm" @click="isEditing = false" />
                    </header>

                    <div class="flex-1 overflow-y-auto p-6">
                        <reverse-relationship-editor
                            ref="editorRef"
                            :items="items"
                            :config="configParameter"
                            :parent-id="meta.id"
                            :mode="config.mode || 'entries'"
                            @saved="onSaved"
                            @saving="editorSaving = $event"
                            @cancel="isEditing = false"
                        />
                    </div>

                    <footer class="bg-gray-200 dark:bg-gray-800 px-6 py-3 flex items-center justify-between border-t dark:border-gray-700">
                        <ui-button variant="ghost" :text="__('Cancel')" @click="isEditing = false" />
                        <ui-button variant="primary" :text="saveButtonText" :disabled="editorSaving" @click="editorRef?.save()" />
                    </footer>
                </div>
            </transition>
        </teleport>
    </div>
</template>

<script setup>
import { ref, inject, computed, onMounted, onBeforeUnmount, getCurrentInstance } from 'vue';
import RelatedItem from './RelatedItem.vue';
import RelatedAsset from './RelatedAsset.vue';
import ReverseRelationshipEditor from './ReverseRelationshipEditor.vue';

const props = defineProps({
    value: { required: true },
    config: { type: Object, default: () => ({}) },
    handle: { type: String, required: true },
    meta: { type: Object, default: () => ({}) },
    readOnly: { type: Boolean, default: false },
    namePrefix: { type: String, default: undefined },
    fieldPathPrefix: { type: String, default: undefined },
});

const storeName = inject('storeName', null);
const { proxy } = getCurrentInstance();

const windowWidth = ref(window.innerWidth);
function onResize() { windowWidth.value = window.innerWidth; }
onMounted(() => window.addEventListener('resize', onResize));
onBeforeUnmount(() => window.removeEventListener('resize', onResize));

const loading = ref(false);
const items = ref([]);
const isEditing = ref(false);
const editorSaving = ref(false);
const editorRef = ref(null);

const saveButtonText = computed(() => editorSaving.value ? proxy.__('Saving...') : proxy.__('Save'));

const allowedConfigKeys = ['mode', 'collection', 'taxonomy', 'container', 'field', 'sort'];

const configParameter = computed(() => {
    const filtered = Object.fromEntries(
        Object.entries(props.config).filter(([key, val]) => allowedConfigKeys.includes(key) && val != null)
    );
    return utf8btoa(JSON.stringify(filtered));
});

function request() {
    if (!props.meta.id) {
        return;
    }

    loading.value = true;

    proxy.$axios.get(cp_url('reverse-relationship'), {
        params: {
            id: props.meta.id,
            config: configParameter.value,
        },
    }).then(response => {
        loading.value = false;
        items.value = response.data.data;
    }).catch(() => {
        loading.value = false;
    });
}

function onSaved(newItems) {
    items.value = newItems;
    isEditing.value = false;
    editorSaving.value = false;
}

onMounted(() => {
    request();
});
</script>
