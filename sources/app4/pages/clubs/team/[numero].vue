<script setup lang="ts">
import type { TeamDetail } from '~/types/clubs'

definePageMeta({
  layout: 'admin',
  middleware: 'auth'
})

const { t } = useI18n()
const api = useApi()
const route = useRoute()
const config = useRuntimeConfig()
const legacyBaseUrl = config.public.legacyBaseUrl as string

const numero = computed(() => Number(route.params.numero))
const team = ref<TeamDetail | null>(null)
const loading = ref(true)

async function loadTeam() {
  loading.value = true
  try {
    team.value = await api.get<TeamDetail>(`/admin/teams/${numero.value}`)
  } catch {
    // useApi already shows toast
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  loadTeam()
})
</script>

<template>
  <div>
    <!-- Back link -->
    <div class="mb-4">
      <NuxtLink
        :to="team ? `/clubs?code=${team.codeClub}` : '/clubs'"
        class="inline-flex items-center gap-1.5 text-sm text-primary-600 dark:text-primary-300 hover:text-primary-800 dark:hover:text-primary-200"
      >
        <UIcon name="i-heroicons-arrow-left" class="w-4 h-4" />
        {{ t('clubs.teams.back_to_club') }}
      </NuxtLink>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex items-center gap-2 text-header-600 dark:text-header-300">
      <UIcon name="i-heroicons-arrow-path" class="w-5 h-5 animate-spin" />
      <span>{{ t('common.loading') }}</span>
    </div>

    <template v-else-if="team">
      <!-- Header -->
      <div class="bg-white dark:bg-header-900 border border-header-200 dark:border-header-700 rounded-lg p-5 mb-4 flex items-start gap-5">
        <div class="flex-1 min-w-0">
          <h1 class="text-2xl font-bold text-header-900 dark:text-header-50 mb-2">
            {{ team.libelle }}
          </h1>

          <div class="flex flex-wrap items-center gap-4 text-sm text-header-900 dark:text-header-50">
          <!-- Club -->
          <div class="flex items-center gap-1.5">
            <UIcon name="i-heroicons-building-office-2" class="w-4 h-4 text-header-600 dark:text-header-300" />
            <span class="font-bold">{{ t('clubs.teams.club') }} :</span>
            <NuxtLink :to="`/clubs?code=${team.codeClub}`" class="text-primary-600 dark:text-primary-300 hover:text-primary-800 dark:hover:text-primary-200">
              {{ team.libelleClub }} ({{ team.codeClub }})
            </NuxtLink>
          </div>

          <!-- Colors -->
          <div v-if="team.color1 || team.color2" class="flex items-center gap-1.5">
            <span class="font-bold">{{ t('clubs.teams.colors') }} :</span>
            <span
              v-if="team.color1"
              class="inline-block w-5 h-5 rounded border border-header-300 dark:border-header-700"
              :style="{ backgroundColor: team.color1 }"
              :title="team.color1"
            />
            <span
              v-if="team.color2"
              class="inline-block w-5 h-5 rounded border border-header-300 dark:border-header-700"
              :style="{ backgroundColor: team.color2 }"
              :title="team.color2"
            />
            <span
              v-if="team.colortext"
              class="inline-block w-5 h-5 rounded border border-header-300 dark:border-header-700 text-[10px] leading-5 text-center font-bold"
              :style="{ backgroundColor: team.colortext }"
              title="Text color"
            >
              A
            </span>
          </div>
          </div>
        </div>

        <!-- Team photo -->
        <div v-if="team.latestPhoto" class="relative shrink-0">
          <img
            :src="`${legacyBaseUrl}/img/KIP/teams/${team.latestPhoto}`"
            :alt="team.libelle"
            class="h-32 w-48 object-cover rounded-lg border border-header-200 dark:border-header-700"
          >
          <span class="absolute bottom-1.5 right-1.5 bg-black/60 text-white text-xs font-medium px-1.5 py-0.5 rounded">
            {{ team.latestPhotoSaison }}
          </span>
        </div>
      </div>

      <!-- Competitions -->
      <div class="bg-white dark:bg-header-900 border border-header-200 dark:border-header-700 rounded-lg p-5">
        <h2 class="text-lg font-semibold text-header-900 dark:text-header-50 mb-3">
          {{ t('clubs.teams.competitions') }}
          <span v-if="team.competitions.length > 0" class="text-sm font-normal text-header-600 dark:text-header-300">
            ({{ team.competitions.length }})
          </span>
        </h2>

        <p v-if="team.competitions.length === 0" class="text-sm text-header-600 dark:text-header-300 italic">
          {{ t('clubs.teams.no_competitions') }}
        </p>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-header-200 dark:border-header-700">
                <th class="text-left py-2 px-3 font-medium text-header-900 dark:text-header-50">{{ t('clubs.teams.season') }}</th>
                <th class="text-left py-2 px-3 font-medium text-header-900 dark:text-header-50">{{ t('clubs.teams.code') }}</th>
                <th class="text-left py-2 px-3 font-medium text-header-900 dark:text-header-50">{{ t('clubs.teams.competition') }}</th>
                <th class="text-left py-2 px-3 font-medium text-header-900 dark:text-header-50">{{ t('clubs.teams.final_ranking') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="(comp, idx) in team.competitions"
                :key="`${comp.codeSaison}-${comp.codeCompet}`"
                :class="idx % 2 === 0 ? 'bg-white dark:bg-header-900' : 'bg-header-50 dark:bg-header-800'"
                class="border-b border-header-100 dark:border-header-800"
              >
                <td class="py-2 px-3 font-mono text-xs text-header-600 dark:text-header-300">{{ comp.codeSaison }}</td>
                <td class="py-2 px-3 font-mono text-xs text-header-600 dark:text-header-300">{{ comp.codeCompet }}</td>
                <td class="py-2 px-3 text-header-900 dark:text-header-50">
                  <span class="align-middle">{{ comp.libelleCompet || comp.codeCompet }}</span>
                  <span
                    class="ml-2 inline-block px-1.5 py-0.5 rounded text-[10px] font-medium bg-header-200 dark:bg-header-700 text-header-900 dark:text-header-50 align-middle"
                  >{{ comp.codeTypeclt }}</span>
                </td>
                <td class="py-2 px-3 font-semibold text-header-900 dark:text-header-50">
                  {{ comp.classementFinal !== null ? comp.classementFinal : '—' }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </template>
  </div>
</template>
