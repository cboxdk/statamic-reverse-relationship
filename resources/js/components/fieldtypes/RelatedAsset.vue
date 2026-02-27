<template>
    <div
        class="shadow-ui-sm relative flex w-full items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-1.5 mb-1.5 last:mb-0 text-base dark:border-gray-700 dark:bg-gray-900"
    >
        <img v-if="asset.thumbnail" :src="asset.thumbnail" class="w-7 h-7 shrink-0 rounded object-cover" :alt="asset.title" />
        <ui-icon v-else name="assets" class="w-7 h-7 shrink-0" />
        <div class="flex flex-1 items-center line-clamp-1 text-sm text-gray-600 dark:text-gray-300">
            <a :href="asset.edit_url" v-text="asset.title" class="line-clamp-1 text-sm text-gray-600 dark:text-gray-300" v-tooltip="asset.title" />
            <div class="flex flex-1 items-center justify-end">
                <div
                    v-if="asset.size"
                    v-text="asset.size"
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
    asset: {
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
