<script setup lang="ts">
import { autocomplete, inputType, isSelectField, isSingleCheckbox, type FormField } from '~/utils/forms'

// One field of a registration form, usable without JavaScript: label, help and error linked by `aria-describedby`
// (FEATURE_FORMS.md § 3.2). `values` are the answers to show again after a refused submission.
const props = defineProps<{ field: FormField, values: string[], error: string | null }>()
const { t } = useI18n()

const id = computed(() => `field-${props.field.key}`)
const helpId = computed(() => (props.field.help ? `${id.value}-help` : null))
const errorId = computed(() => (props.error ? `${id.value}-error` : null))
const describedBy = computed(() => [helpId.value, errorId.value].filter(Boolean).join(' ') || undefined)
const isGroup = computed(() => props.field.type === 'radio' || (props.field.type === 'checkbox' && !isSingleCheckbox(props.field)))
const errorMessage = computed(() => (props.error
  ? t(`forms.error.${props.error}`, { min: props.field.min ?? '', max: props.field.max ?? '' })
  : null))
const checked = (value: string) => props.values.includes(value)
const controlClass = 'w-full rounded border px-2 py-1'
</script>

<template>
  <div class="space-y-1" :data-field="field.key">
    <!-- Choices: radios or checkboxes in a fieldset -->
    <fieldset v-if="isGroup" :id="id" :aria-describedby="describedBy" tabindex="-1">
      <legend class="font-semibold">
        {{ field.label }}<span v-if="field.required" aria-hidden="true"> *</span>
        <span v-if="field.required" class="sr-only">({{ $t('forms.required') }})</span>
      </legend>
      <label v-for="option in field.options" :key="option.value" class="flex items-center gap-2">
        <input
          :type="field.type === 'radio' ? 'radio' : 'checkbox'"
          :name="field.key"
          :value="option.value"
          :checked="checked(option.value)"
          :required="field.type === 'radio' && field.required"
        >
        {{ option.label }}
      </label>
    </fieldset>

    <!-- Single yes / no box -->
    <label v-else-if="isSingleCheckbox(field)" class="flex items-center gap-2 font-semibold">
      <input :id="id" type="checkbox" :name="field.key" value="1" :checked="values.length > 0" :required="field.required" :aria-describedby="describedBy" :aria-invalid="error ? true : undefined">
      {{ field.label }}<span v-if="field.required" aria-hidden="true"> *</span>
    </label>

    <template v-else>
      <label :for="id" class="block font-semibold">
        {{ field.label }}<span v-if="field.required" aria-hidden="true"> *</span>
      </label>
      <select
        v-if="isSelectField(field.type)"
        :id="id"
        :name="field.key"
        :required="field.required"
        :aria-describedby="describedBy"
        :aria-invalid="error ? true : undefined"
        :class="[controlClass, error ? 'border-kpi-red-600' : 'border-line']"
      >
        <option value="">{{ $t('forms.choose') }}</option>
        <option v-for="option in field.options" :key="option.value" :value="option.value" :selected="checked(option.value)">{{ option.label }}</option>
      </select>
      <textarea
        v-else-if="field.type === 'textarea'"
        :id="id"
        :name="field.key"
        :required="field.required"
        rows="5"
        maxlength="5000"
        :aria-describedby="describedBy"
        :aria-invalid="error ? true : undefined"
        :class="[controlClass, error ? 'border-kpi-red-600' : 'border-line']"
        :value="values[0] ?? ''"
      />
      <input
        v-else
        :id="id"
        :type="inputType(field.type) ?? 'text'"
        :name="field.key"
        :value="values[0] ?? ''"
        :required="field.required"
        :min="field.min ?? undefined"
        :max="field.max ?? undefined"
        :autocomplete="autocomplete(field.type)"
        :aria-describedby="describedBy"
        :aria-invalid="error ? true : undefined"
        :class="[controlClass, error ? 'border-kpi-red-600' : 'border-line']"
      >
    </template>

    <p v-if="helpId" :id="helpId" class="text-sm text-ink/70">{{ field.help }}</p>
    <p v-if="errorId" :id="errorId" class="text-sm font-semibold text-kpi-red-600" data-testid="field-error">{{ errorMessage }}</p>
  </div>
</template>
