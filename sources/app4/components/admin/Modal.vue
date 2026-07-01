<script setup lang="ts">
interface Props {
  open?: boolean
  title?: string
  maxWidth?: 'sm' | 'md' | 'lg' | 'xl' | '2xl' | '3xl'
}

withDefaults(defineProps<Props>(), {
  open: false,
  title: '',
  maxWidth: 'md'
})

const emit = defineEmits<{
  close: []
}>()

const maxWidthClasses = {
  sm: 'max-w-sm',
  md: 'max-w-md',
  lg: 'max-w-lg',
  xl: 'max-w-xl',
  '2xl': 'max-w-2xl',
  '3xl': 'max-w-3xl'
}
</script>

<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="fixed inset-0 z-9999 flex items-center justify-center p-4"
      @click.self="emit('close')"
    >
      <!-- Backdrop -->
      <div class="absolute inset-0 bg-black/50" @click="emit('close')" />

      <!-- Modal content -->
      <div
        class="relative bg-white dark:bg-header-900 rounded-lg shadow-xl w-full max-h-[90vh] overflow-y-auto"
        :class="maxWidthClasses[maxWidth]"
      >
        <!-- Header -->
        <div v-if="title || $slots.header" class="flex items-center justify-between p-6 border-b border-header-200 dark:border-header-800">
          <slot name="header">
            <h3 class="text-lg font-semibold text-header-900 dark:text-header-50">
              {{ title }}
            </h3>
          </slot>
          <button
            type="button"
            class="text-header-600 dark:text-header-600 hover:text-header-900 dark:hover:text-header-200 p-1"
            @click="emit('close')"
          >
            <UIcon name="heroicons:x-mark" class="w-6 h-6" />
          </button>
        </div>

        <!-- Body -->
        <div class="p-6 text-header-900 dark:text-header-100">
          <slot />
        </div>

        <!-- Footer -->
        <div
          v-if="$slots.footer"
          class="flex justify-end gap-2 p-6 pt-0"
        >
          <slot name="footer" />
        </div>
      </div>
    </div>
  </Teleport>
</template>
