<script setup lang="ts">
interface Props {
  variant?: 'primary' | 'secondary' | 'danger' | 'ghost'
  size?: 'sm' | 'md'
  icon?: string
  loading?: boolean
  disabled?: boolean
}

withDefaults(defineProps<Props>(), {
  variant: 'secondary',
  size: 'md',
  icon: '',
  loading: false,
  disabled: false
})

const variantClasses = {
  primary: 'text-white bg-primary-600 border-primary-600 hover:bg-primary-700',
  secondary: 'text-header-900 dark:text-header-50 bg-white dark:bg-header-900 border-header-300 dark:border-header-700 hover:bg-header-50 dark:hover:bg-header-800',
  danger: 'text-danger-700 dark:text-danger-400 bg-white dark:bg-header-900 border-danger-300 dark:border-danger-700 hover:bg-danger-50 dark:hover:bg-danger-900',
  ghost: 'text-header-900 dark:text-header-50 bg-transparent border-transparent hover:bg-header-200'
}

const sizeClasses = {
  sm: 'px-2 py-1 text-xs gap-1',
  md: 'px-3 py-2 text-sm gap-2'
}

const iconSizeClasses = {
  sm: 'w-3.5 h-3.5',
  md: 'w-4 h-4'
}
</script>

<template>
  <button
    :class="[
      'inline-flex items-center font-medium border rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed',
      variantClasses[variant],
      sizeClasses[size]
    ]"
    :disabled="disabled || loading"
  >
    <UIcon
      v-if="loading"
      name="heroicons:arrow-path"
      :class="['animate-spin', iconSizeClasses[size]]"
    />
    <UIcon
      v-else-if="icon"
      :name="icon"
      :class="iconSizeClasses[size]"
    />
    <slot />
  </button>
</template>
