<template>
    <div
        class="shadow-ui-sm relative flex w-full items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-1.5 mb-1.5 last:mb-0 text-base dark:border-gray-700 dark:bg-gray-900"
    >
        <div class="flex flex-1 items-center line-clamp-1 text-sm text-gray-600 dark:text-gray-300">
            <ui-status-indicator v-if="item.status" :status="item.status" class="me-2" />

            <a
                :href="item.edit_url"
                v-text="item.title"
                class="line-clamp-1 text-sm text-gray-600 dark:text-gray-300"
                v-tooltip="item.title"
            />

            <div class="flex flex-1 items-center justify-end">
                <div
                    v-if="item.hint || item.collection"
                    v-text="item.hint || (item.collection && item.collection.title)"
                    class="text-2xs tracking-tight me-2 hidden whitespace-nowrap text-gray-500 @sm:block"
                />

                <div v-if="removable" class="flex items-center">
                    <ui-dropdown>
                        <template #trigger>
                            <ui-button icon="dots" variant="ghost" size="xs" :aria-label="__('Open dropdown menu')" />
                        </template>
                        <ui-dropdown-menu>
                            <ui-dropdown-item :text="__('Unlink')" variant="destructive" @click="$emit('remove')" />
                        </ui-dropdown-menu>
                    </ui-dropdown>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
defineProps({
    item: {
        type: Object,
        required: true,
    },
    removable: {
        type: Boolean,
        default: false,
    },
});

defineEmits(['remove']);
</script>
