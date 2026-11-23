<script setup lang="ts">
import { canAddHolder, holderLabel, rowKindOf, scopeCells, statusClasses, type CoverageCell, type CoverageHolder, type DesignationTarget, type RoleDefinition, type ScopeType } from '~/utils/designations'

/**
 * Bloc « Désignations » d'un périmètre (FEATURE_DESIGNATIONS.md § 8.4, ticket designations/08) : compétition,
 * événement, journée ou équipe. Sur une journée, les titulaires hérités (compétition, événement) sont visibles et
 * l'état de chaque rôle est celui du suivi ; ailleurs, le bloc liste les Désignations propres du périmètre.
 * Désigner et retirer passent par l'API, qui applique les droits (profils 1–2, délégué CNA pour le jury).
 */
const props = defineProps<{ season: string, scopeType: ScopeType, scopeId: string | number, label: string }>()
const emit = defineEmits<{ changed: [] }>()
const { t } = useI18n()
const api = useApi()
const toast = useToast()
const authStore = useAuthStore()

const roles = ref<Record<string, RoleDefinition>>({})
const cells = ref<Record<string, CoverageCell>>({})
const loading = ref(false)
const target = ref<DesignationTarget | null>(null)
const removing = ref<CoverageHolder | null>(null)
const kind = computed(() => rowKindOf(props.scopeType)!)
/** Périmètres auxquels un rapport officiel peut être rattaché (D12, D14). */
const REPORT_SCOPES: ScopeType[] = ['COMPETITION', 'EVENEMENT', 'JOURNEE']
const canWrite = computed(() => authStore.profile <= 5)

const load = async () => {
  loading.value = true
  try {
    if (Object.keys(roles.value).length === 0) {
      roles.value = await api.get<Record<string, RoleDefinition>>('/admin/designations/roles')
    }
    if (props.scopeType === 'JOURNEE') {
      cells.value = (await api.get<{ roles: Record<string, CoverageCell> }>('/admin/designations/effective', { gameday: String(props.scopeId) })).roles
    } else {
      const list = await api.get<{ items: Parameters<typeof scopeCells>[2] }>('/admin/designations', { season: props.season, scopeType: props.scopeType, scopeId: String(props.scopeId) })
      cells.value = scopeCells(props.scopeType, Object.values(roles.value), list.items)
    }
  } finally {
    loading.value = false
  }
}

const openAdd = (role: string) => {
  target.value = { role, label: props.label, scopeType: props.scopeType, scopeIds: [String(props.scopeId)] }
}

const saved = async () => {
  target.value = null
  await load()
  emit('changed')
}

const confirmRemove = async () => {
  if (!removing.value) return
  try {
    for (const id of removing.value.designationIds ?? [removing.value.designationId]) {
      await api.del(`/admin/designations/${id}`)
    }
    toast.add({ title: t('designations.removed'), color: 'success' })
    await load()
    emit('changed')
  } finally {
    removing.value = null
  }
}

watch(() => [props.scopeType, props.scopeId, props.season], load, { immediate: true })
</script>

<template>
  <div>
    <p v-if="loading" class="text-sm text-header-500">{{ t('common.loading') }}</p>
    <table v-else class="min-w-full text-sm border border-header-200 dark:border-header-700">
      <caption class="sr-only">{{ t('designations.block.caption', { line: label }) }}</caption>
      <tbody>
        <tr v-for="(cell, role) in cells" :key="role">
          <th scope="row" class="px-2 py-1 text-left font-medium w-1/3">{{ roles[role]?.label ?? role }}</th>
          <td class="px-2 py-1" :class="statusClasses(cell.status)">
            <DesignationsCellHolders
              :cell="cell"
              :kind="kind"
              :can-add="canWrite && canAddHolder(cell, kind, roles[role])"
              @add="openAdd(String(role))"
              @remove="removing = $event"
            />
          </td>
        </tr>
      </tbody>
    </table>
    <p class="text-xs text-header-500 mt-2">{{ t('designations.block.help') }}</p>
    <NuxtLink
      v-if="REPORT_SCOPES.includes(scopeType)"
      :to="{ path: '/reports', query: { scopeType, scopeId: String(scopeId), label } }"
      class="inline-block mt-2 text-sm underline"
    >
      {{ t('reports.write') }}
    </NuxtLink>

    <DesignationsAddModal :target="target" :season="season" :roles="roles" @close="target = null" @saved="saved" />
    <AdminConfirmModal
      :open="removing !== null"
      :title="t('designations.remove.title')"
      :message="removing ? t('designations.remove.message', { name: holderLabel(removing) }) : ''"
      :confirm-text="t('designations.remove.confirm')"
      :cancel-text="t('common.cancel')"
      @confirm="confirmRemove"
      @close="removing = null"
    />
  </div>
</template>
