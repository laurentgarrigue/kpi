<script setup lang="ts">
import type { PlayerAutocomplete } from '~/types'
import { scopeOfRow, type DesignationTarget, type RoleDefinition } from '~/utils/designations'

/**
 * Désigner un licencié (FEATURE_DESIGNATIONS.md § 8.4) : depuis une case du suivi ou depuis un bloc « Désignations ».
 * Une cible à plusieurs périmètres (phases d'un tournoi regroupées) crée une Désignation par périmètre.
 */

const props = defineProps<{ target: DesignationTarget | null, season: string, roles: Record<string, RoleDefinition> }>()
const emit = defineEmits<{ close: [], saved: [] }>()
const { t } = useI18n()
const api = useApi()
const toast = useToast()

const person = ref<PlayerAutocomplete | null>(null)
const draft = reactive({ email: '', authority: 'CNA', order: 1, quality: '' })
const saving = ref(false)
/** null = pas encore vérifié ; l'email n'est demandé que si le licencié n'a pas de compte KPI (D5). */
const hasAccount = ref<boolean | null>(null)
const role = computed(() => (props.target ? props.roles[props.target.role] : undefined))
const roleLabel = (code: string) => props.roles[code]?.label ?? code

const scopes = (target: DesignationTarget) => (target.kind
  ? (target.ids ?? []).map(id => scopeOfRow(target.kind!, id))
  : (target.scopeIds ?? []).map(scopeId => ({ scopeType: target.scopeType!, scopeId })))

watch(() => props.target, () => {
  person.value = null
  Object.assign(draft, { email: '', authority: 'CNA', order: 1, quality: '' })
})

watch(person, async (selected) => {
  hasAccount.value = null
  draft.email = ''
  if (selected && role.value?.mandateProfile) {
    hasAccount.value = (await api.get<{ exists: boolean }>('/admin/designations/account', { matric: String(selected.matric) })).exists
  }
})

const save = async () => {
  if (!props.target || !person.value) return
  saving.value = true
  try {
    let account: 'created' | 'existing' | 'none' = 'none'
    for (const scope of scopes(props.target)) {
      const created = await api.post<{ account: 'created' | 'existing' | 'none' }>('/admin/designations', {
        season: props.season,
        role: props.target.role,
        ...scope,
        matric: person.value.matric,
        authority: draft.authority,
        order: draft.order,
        quality: draft.quality || null,
        email: draft.email || null,
      })
      account = created.account
    }
    toast.add({ title: t(`designations.account.${account}`), color: account === 'none' && role.value?.mandateProfile ? 'warning' : 'success' })
    emit('saved')
  } catch {
    // useApi affiche déjà l'erreur
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <AdminModal :open="target !== null" focus-first-field :title="target ? t('designations.add.title', { role: roleLabel(target.role), line: target.label }) : ''" @close="emit('close')">
    <form v-if="target" class="space-y-4" @submit.prevent="save">
      <div>
        <label class="block text-sm font-medium mb-1">{{ t('designations.add.person') }} *</label>
        <AdminPlayerAutocomplete v-model="person" :placeholder="t('designations.add.person_placeholder')" />
      </div>
      <div v-if="role?.mandateProfile && person && hasAccount === false">
        <label class="block text-sm font-medium mb-1" for="designation-email">{{ t('designations.add.email') }}</label>
        <input id="designation-email" v-model="draft.email" type="email" class="w-full rounded border border-header-300 dark:border-header-700 bg-white dark:bg-header-900 text-header-900 dark:text-header-50 px-2 py-1" aria-describedby="designation-email-help">
        <p id="designation-email-help" class="text-xs text-header-500 mt-1">{{ t('designations.add.email_help') }}</p>
      </div>
      <div v-if="(role?.authorities.length ?? 0) > 1">
        <label class="block text-sm font-medium mb-1" for="designation-authority">{{ t('designations.add.authority') }}</label>
        <select id="designation-authority" v-model="draft.authority" class="w-full rounded border border-header-300 dark:border-header-700 bg-white dark:bg-header-900 text-header-900 dark:text-header-50 px-2 py-1">
          <option v-for="authority in role?.authorities ?? []" :key="authority" :value="authority">{{ t(`designations.authority.${authority}`) }}</option>
        </select>
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-sm font-medium mb-1" for="designation-order">{{ t('designations.add.order') }}</label>
          <input id="designation-order" v-model.number="draft.order" type="number" min="1" max="99" class="w-full rounded border border-header-300 dark:border-header-700 bg-white dark:bg-header-900 text-header-900 dark:text-header-50 px-2 py-1">
        </div>
        <div>
          <label class="block text-sm font-medium mb-1" for="designation-quality">{{ t('designations.add.quality') }}</label>
          <input id="designation-quality" v-model="draft.quality" type="text" maxlength="100" class="w-full rounded border border-header-300 dark:border-header-700 bg-white dark:bg-header-900 text-header-900 dark:text-header-50 px-2 py-1">
        </div>
      </div>
      <div class="flex justify-end gap-2">
        <button type="button" class="px-3 py-1.5 rounded border border-header-300 dark:border-header-700 text-sm" @click="emit('close')">{{ t('common.cancel') }}</button>
        <button type="submit" class="px-3 py-1.5 rounded bg-primary-600 text-white text-sm disabled:opacity-50" :disabled="!person || saving">{{ t('designations.add.submit') }}</button>
      </div>
    </form>
  </AdminModal>
</template>
