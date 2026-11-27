<script setup lang="ts">
/**
 * GamedaysOrganiserEditor — club organisateur d'une journée dans la fenêtre « Officiels » de la page Journées
 * (FEATURE_DESIGNATIONS.md ORG-04), comme dans Désignations : bouton crayon → fenêtre de recherche dans la liste des clubs
 * (code + libellé, ORG-01) ; bouton corbeille → confirmation avant de le retirer.
 */
import { statusClasses } from '~/utils/designations'

interface Organiser { code: string, label: string }

const props = defineProps<{ gamedayId: number, organiser: Organiser, readonly?: boolean }>()
const emit = defineEmits<{ saved: [organiser: Organiser] }>()
const { t } = useI18n()
const api = useApi()
const toast = useToast()

/** Taille de la colonne kp_journee.Organisateur. */
const ORGANISER_LABEL_MAX = 40
const saving = ref(false)
const editing = ref(false)
const clearing = ref(false)

const save = async (organiser: Organiser) => {
  saving.value = true
  try {
    await api.put(`/admin/gamedays/${props.gamedayId}`, { organisateur: organiser.label, codeOrganisateur: organiser.code })
    toast.add({ title: t('designations.organiser_saved'), color: 'success' })
    editing.value = false
    emit('saved', organiser)
  } catch {
    // useApi affiche déjà l'erreur
  } finally {
    saving.value = false
  }
}

const confirmClear = async () => {
  clearing.value = false
  await save({ code: '', label: '' })
}

const choose = (club: { code: string, libelle: string }) => save({ code: club.code, label: club.libelle.slice(0, ORGANISER_LABEL_MAX) })
</script>

<template>
  <!-- Même présentation et mêmes gestes que Désignations (libellé | valeur), vert quand le club est renseigné -->
  <table class="min-w-full text-sm border border-header-200 dark:border-header-700">
    <tbody>
      <tr>
        <th scope="row" class="px-2 py-1 text-left font-medium w-1/3">{{ t('gamedays.field.club_organisateur') }}</th>
        <td class="px-2 py-1" :class="statusClasses(organiser.label ? 'green' : 'empty')">
          <div class="flex items-center gap-1">
            <span>{{ organiser.label || '-' }}</span>
            <span v-if="organiser.code" class="font-mono text-xs">({{ organiser.code }})</span>
            <button
              v-if="!readonly"
              type="button"
              class="text-primary-600 dark:text-primary-300 hover:text-primary-800 dark:hover:text-primary-200"
              :title="t('designations.organiser_edit')"
              :aria-label="t('designations.organiser_edit')"
              @click="editing = true"
            >
              <UIcon name="heroicons:pencil-square" class="w-5 h-5" />
            </button>
            <button
              v-if="!readonly && organiser.label"
              type="button"
              class="text-danger-600 dark:text-danger-300 hover:text-danger-800 dark:hover:text-danger-200 disabled:opacity-50"
              :title="t('designations.organiser_clear')"
              :aria-label="t('designations.organiser_clear')"
              :disabled="saving"
              @click="clearing = true"
            >
              <UIcon name="heroicons:trash" class="w-5 h-5" />
            </button>
          </div>
        </td>
      </tr>
    </tbody>
  </table>

  <!-- Recherche dans une fenêtre, comme dans Désignations -->
  <AdminModal :open="editing" focus-first-field :title="t('designations.organiser_edit')" @close="editing = false">
    <div v-if="editing" class="space-y-3 min-h-72">
      <AdminTextAutocomplete
        model-value=""
        api-url="/admin/clubs/search-all"
        label-field="libelle"
        detail-field="code"
        :placeholder="t('gamedays.field.club_organisateur')"
        @select="choose"
      />
    </div>
  </AdminModal>

  <AdminConfirmModal
    :open="clearing"
    :title="t('designations.organiser_clear_title')"
    :message="t('designations.organiser_clear_message', { club: organiser.label })"
    :confirm-text="t('designations.remove.confirm')"
    :cancel-text="t('common.cancel')"
    @confirm="confirmClear"
    @close="clearing = false"
  />
</template>
