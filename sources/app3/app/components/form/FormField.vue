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
// Text areas and choices take the whole width of the two-column form (FRM-12).
const wide = computed(() => isGroup.value || props.field.type === 'textarea' || isSingleCheckbox(props.field))
const controlClass = 'w-full rounded-md border bg-surface px-3 py-2'

// Quantities: − / + buttons around the number, within its bounds (FRM-12); the field stays typable.
const number = useTemplateRef<HTMLInputElement>('number')
function step(delta: number) {
  if (!number.value) {
    return
  }
  const next = (Number(number.value.value) || 0) + delta
  const bounded = Math.min(props.field.max ?? Number.POSITIVE_INFINITY, Math.max(props.field.min ?? 0, next))
  number.value.value = String(bounded)
  number.value.dispatchEvent(new Event('input', { bubbles: true }))
}
</script>

<template>
  <div class="grid content-start gap-1.5" :class="{ 'md:col-span-2': wide }" :data-field="field.key">
    <!-- Choices: radios or checkboxes in a fieldset, as cards -->
    <fieldset v-if="isGroup" :id="id" :aria-describedby="describedBy" tabindex="-1" class="grid gap-2">
      <legend class="mb-1.5 text-sm font-semibold">
        {{ field.label }}<FormOptionalMark v-if="!field.required" />
      </legend>
      <label
        v-for="option in field.options"
        :key="option.value"
        class="flex cursor-pointer items-center gap-2.5 rounded-md border border-line px-3 py-2.5 has-[:checked]:border-navy has-[:checked]:bg-wash"
      >
        <input
          :type="field.type === 'radio' ? 'radio' : 'checkbox'"
          :name="field.key"
          :value="option.value"
          :checked="checked(option.value)"
          :required="field.type === 'radio' && field.required"
          class="size-4.5 accent-navy"
        >
        {{ option.label }}
      </label>
    </fieldset>

    <!-- Single yes / no box -->
    <label v-else-if="isSingleCheckbox(field)" class="flex cursor-pointer items-center gap-2.5 rounded-md border border-line px-3 py-2.5 text-sm font-semibold has-[:checked]:border-navy has-[:checked]:bg-wash">
      <input :id="id" type="checkbox" :name="field.key" value="1" :checked="values.length > 0" :required="field.required" :aria-describedby="describedBy" :aria-invalid="error ? true : undefined" class="size-4.5 accent-navy">
      {{ field.label }}<FormOptionalMark v-if="!field.required" />
    </label>

    <template v-else>
      <label :for="id" class="block text-sm font-semibold">
        {{ field.label }}<FormOptionalMark v-if="!field.required" />
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
        rows="4"
        maxlength="5000"
        :aria-describedby="describedBy"
        :aria-invalid="error ? true : undefined"
        :class="[controlClass, 'min-h-24 resize-y', error ? 'border-kpi-red-600' : 'border-line']"
        :value="values[0] ?? ''"
      />
      <span v-else-if="field.type === 'number'" class="inline-grid w-fit grid-cols-[2.125rem_3.5rem_2.125rem] overflow-hidden rounded-md border" :class="error ? 'border-kpi-red-600' : 'border-line'">
        <button type="button" class="bg-wash text-lg font-semibold" data-step="-1" :aria-label="$t('forms.less')" @click="step(-1)">−</button>
        <input
          :id="id"
          ref="number"
          type="number"
          inputmode="numeric"
          :name="field.key"
          :value="values[0] ?? ''"
          :required="field.required"
          :min="field.min ?? undefined"
          :max="field.max ?? undefined"
          :aria-describedby="describedBy"
          :aria-invalid="error ? true : undefined"
          class="w-full bg-surface py-2 text-center [appearance:textfield]"
        >
        <button type="button" class="bg-wash text-lg font-semibold" data-step="1" :aria-label="$t('forms.more')" @click="step(1)">+</button>
      </span>
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

    <p v-if="helpId" :id="helpId" class="text-sm text-muted">{{ field.help }}</p>
    <p v-if="errorId" :id="errorId" class="text-sm font-semibold text-kpi-red-600" data-testid="field-error">{{ errorMessage }}</p>
  </div>
</template>
