<script setup lang="ts">
interface Props {
  active?: boolean
  activeIcon?: string
  inactiveIcon?: string
  activeColor?: 'success' | 'primary' | 'danger' | 'warning'
  activeTitle?: string
  inactiveTitle?: string
  size?: 'sm' | 'md' | 'lg'
  disabled?: boolean
}

withDefaults(defineProps<Props>(), {
  active: false,
  activeIcon: 'heroicons:check-circle-solid',
  inactiveIcon: 'heroicons:x-circle-solid',
  activeColor: 'success',
  activeTitle: '',
  inactiveTitle: '',
  size: 'md',
  disabled: false
})

const emit = defineEmits<{
  (e: 'toggle'): void
}>()

const activeColorClasses = {
  success: 'text-success-700 dark:text-success-300 hover:text-success-600',
  primary: 'text-primary-500 dark:text-primary-300 hover:text-primary-600',
  danger: 'text-danger-600 dark:text-danger-300 hover:text-danger-600',
  warning: 'text-warning-600 dark:text-warning-300 hover:text-warning-600'
}

const sizeClasses = {
  sm: 'w-5 h-5',
  md: 'w-6 h-6',
  lg: 'w-8 h-8'
}
</script>

<template>
  <button
    :class="[
      'p-1.5 transition-colors',
      active ? activeColorClasses[activeColor] : 'text-header-600 dark:text-header-300',
      disabled ? 'opacity-40 cursor-not-allowed' : 'hover:opacity-80'
    ]"
    :title="active ? activeTitle : inactiveTitle"
    :disabled="disabled"
    @click="!disabled && emit('toggle')"
  >
    <UIcon
      :name="active ? activeIcon : inactiveIcon"
      :class="sizeClasses[size]"
    />
  </button>
</template>
