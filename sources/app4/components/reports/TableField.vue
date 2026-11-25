<script setup lang="ts">
import { canAddRows, localized, withCell, type ReportFieldDefinition, type ReportRow } from '~/utils/reports'

/** Champ tableau d'un rapport : officiels, arbitres (lignes pré-remplies depuis KPI), infractions (lignes libres). */
const props = defineProps<{ field: ReportFieldDefinition, rows: ReportRow[], disabled: boolean, error?: string }>()
const emit = defineEmits<{ update: [rows: ReportRow[]] }>()
const { t, locale } = useI18n()
const columns = computed(() => props.field.columns ?? [])
const optionLabel = (key: string, option: string) => t(`reports.option.${key}.${option}`, option)
</script>

<template>
  <div class="md:col-span-2">
    <p class="text-sm font-medium mb-1">{{ localized(field.label, locale) }}</p>
    <div class="overflow-x-auto">
      <table class="min-w-full text-sm border border-header-200 dark:border-header-700">
        <thead>
          <tr class="bg-header-50 dark:bg-header-800">
            <th v-for="column in columns" :key="column.key" scope="col" class="px-2 py-1 text-left">{{ localized(column.label, locale) }}</th>
            <th v-if="canAddRows(field) && !disabled" scope="col"><span class="sr-only">{{ t('reports.table.actions') }}</span></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(row, index) in rows" :key="index">
            <td v-for="column in columns" :key="column.key" class="px-2 py-1">
              <span v-if="column.readonly">{{ row[column.key] }}</span>
              <input v-else-if="column.type === 'checkbox'" type="checkbox" :checked="row[column.key] === true" :disabled="disabled" :aria-label="localized(column.label, locale)" @change="emit('update', withCell(rows, index, column.key, ($event.target as HTMLInputElement).checked))">
              <select v-else-if="column.type === 'select'" :value="(row[column.key] as string) ?? ''" :disabled="disabled" :aria-label="localized(column.label, locale)" class="rounded border border-header-300 dark:border-header-700 bg-white dark:bg-header-900 text-header-900 dark:text-header-50 px-2 py-1 text-sm" @change="emit('update', withCell(rows, index, column.key, ($event.target as HTMLSelectElement).value || null))">
                <option value="">—</option>
                <option v-for="option in column.options ?? []" :key="option" :value="option">{{ optionLabel(column.key, option) }}</option>
              </select>
              <input v-else type="text" :value="(row[column.key] as string) ?? ''" :disabled="disabled" :aria-label="localized(column.label, locale)" class="w-full rounded border border-header-300 dark:border-header-700 bg-white dark:bg-header-900 text-header-900 dark:text-header-50 px-2 py-1 text-sm" @input="emit('update', withCell(rows, index, column.key, ($event.target as HTMLInputElement).value))">
            </td>
            <td v-if="canAddRows(field) && !disabled" class="px-2 py-1">
              <button type="button" class="text-xs underline" @click="emit('update', rows.filter((_, i) => i !== index))">{{ t('reports.table.remove') }}</button>
            </td>
          </tr>
          <tr v-if="rows.length === 0">
            <td :colspan="columns.length + 1" class="px-2 py-1 text-header-500">{{ t('reports.table.empty') }}</td>
          </tr>
        </tbody>
      </table>
    </div>
    <button v-if="canAddRows(field) && !disabled" type="button" class="mt-1 text-sm underline" @click="emit('update', [...rows, {}])">+ {{ t('reports.table.add') }}</button>
    <p v-if="error" class="text-xs text-red-700 dark:text-red-300 mt-1">{{ t(`reports.error.${error}`) }}</p>
  </div>
</template>
