<script setup lang="ts">
/** Choix des rôles affichés dans le suivi des Désignations ; aucun choix = tous. */
const props = defineProps<{ modelValue: string[], groups: { key: string, label: string, roles: { code: string, label: string }[] }[] }>()
const emit = defineEmits<{ 'update:modelValue': [value: string[]] }>()
const { t } = useI18n()

const toggle = (code: string) => emit('update:modelValue', props.modelValue.includes(code)
  ? props.modelValue.filter(c => c !== code)
  : [...props.modelValue, code])
</script>

<template>
  <details class="relative">
    <summary class="cursor-pointer list-none px-3 py-1.5 text-sm rounded border border-header-300 dark:border-header-700 bg-white dark:bg-header-900">
      {{ t('designations.roles_picker.label') }} : {{ modelValue.length === 0 ? t('designations.roles_picker.all') : modelValue.length }}
    </summary>
    <div class="absolute z-20 mt-1 w-72 p-3 rounded-lg shadow-lg border border-header-200 dark:border-header-700 bg-white dark:bg-header-900">
      <button type="button" class="text-xs underline mb-2" @click="emit('update:modelValue', [])">{{ t('designations.roles_picker.all') }}</button>
      <fieldset v-for="group in props.groups" :key="group.key" class="mt-2 pt-2 border-t border-header-100 dark:border-header-800">
        <legend class="text-xs font-semibold uppercase tracking-wider text-header-600 dark:text-header-300">{{ group.label }}</legend>
        <label v-for="role in group.roles" :key="role.code" class="flex items-center gap-1.5 text-sm mb-0.5 cursor-pointer">
          <input type="checkbox" :checked="modelValue.includes(role.code)" @change="toggle(role.code)">
          {{ role.label }}
        </label>
      </fieldset>
    </div>
  </details>
</template>
