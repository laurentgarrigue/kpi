<script setup lang="ts">
import { computedValue, localized, type ReportFieldDefinition } from '~/utils/reports'

/** Un champ de rapport rendu depuis sa définition du catalogue (RAP-01). */
const props = defineProps<{ field: ReportFieldDefinition, answers: Record<string, unknown>, disabled: boolean, error?: string }>()
const emit = defineEmits<{ update: [key: string, value: unknown] }>()
const { t, locale } = useI18n()
const id = computed(() => `report-field-${props.field.key}`)
const value = computed(() => props.answers[props.field.key])
const inputClass = 'w-full rounded border border-header-300 dark:border-header-700 bg-white dark:bg-header-900 text-header-900 dark:text-header-50 px-2 py-1 text-sm disabled:opacity-70'
const asNumber = (raw: string) => (raw === '' ? null : Number(raw))
</script>

<template>
  <div>
    <label v-if="field.type !== 'checkbox'" :for="id" class="block text-sm font-medium mb-1">
      {{ localized(field.label, locale) }}<span v-if="field.required" aria-hidden="true"> *</span>
    </label>
    <textarea v-if="field.type === 'textarea'" :id="id" :value="(value as string) ?? ''" rows="4" :class="inputClass" :disabled="disabled" :required="field.required" :aria-invalid="!!error" :aria-describedby="error ? `${id}-error` : undefined" @input="emit('update', field.key, ($event.target as HTMLTextAreaElement).value)" />
    <select v-else-if="field.type === 'select'" :id="id" :value="(value as string) ?? ''" :class="inputClass" :disabled="disabled" :aria-invalid="!!error" @change="emit('update', field.key, ($event.target as HTMLSelectElement).value)">
      <option value="">—</option>
      <option v-for="option in field.options ?? []" :key="option" :value="option">{{ t(`reports.option.${field.key}.${option}`, option) }}</option>
    </select>
    <label v-else-if="field.type === 'checkbox'" :for="id" class="inline-flex items-center gap-2 text-sm">
      <input :id="id" type="checkbox" :checked="value === true" :disabled="disabled" @change="emit('update', field.key, ($event.target as HTMLInputElement).checked)">
      {{ localized(field.label, locale) }}
    </label>
    <p v-else-if="field.type === 'sum'" :id="id" class="text-sm font-semibold">{{ computedValue(field, answers) }} / {{ field.max }}</p>
    <div v-else-if="field.type === 'score' || field.type === 'number'" class="flex items-center gap-2">
      <input :id="id" type="number" :min="field.type === 'score' ? 0 : undefined" :max="field.max" :value="(value as number) ?? ''" class="w-24 rounded border border-header-300 dark:border-header-700 bg-white dark:bg-header-900 text-header-900 dark:text-header-50 px-2 py-1 text-sm" :disabled="disabled" :aria-invalid="!!error" @input="emit('update', field.key, asNumber(($event.target as HTMLInputElement).value))">
      <span v-if="field.type === 'score'" class="text-sm">/ {{ field.max }}</span>
    </div>
    <input v-else :id="id" :type="field.type" :value="(value as string) ?? ''" :class="inputClass" :disabled="disabled" :aria-invalid="!!error" :aria-describedby="error ? `${id}-error` : undefined" @input="emit('update', field.key, ($event.target as HTMLInputElement).value)">
    <p v-if="error" :id="`${id}-error`" class="text-xs text-red-700 dark:text-red-300 mt-1">{{ t(`reports.error.${error}`) }}</p>
  </div>
</template>
