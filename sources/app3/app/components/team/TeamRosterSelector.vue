<script setup lang="ts">
import { rosterKey, type TeamSeason } from '~/utils/teams'

// Season + competition of the roster: a GET form (`?roster=season|code`), applied on change with JavaScript (TEA-03).
const props = defineProps<{ seasons: TeamSeason[], season: string, competition: string, path: string }>()
const localePath = useLocalePath()

function apply(event: Event) {
  navigateTo({ path: localePath(props.path), query: { roster: (event.target as HTMLSelectElement).value } })
}
</script>

<template>
  <form method="get" :action="localePath(path)" class="flex flex-wrap items-end gap-4" data-testid="roster-selector">
    <label class="flex w-full flex-col text-sm text-muted sm:w-auto">
      {{ $t('teams.rosterSelect') }}
      <select name="roster" class="w-full max-w-full rounded-md border border-line bg-surface px-2.5 py-1.5 text-ink sm:w-auto" @change="apply">
        <optgroup v-for="item in seasons" :key="item.season" :label="item.season">
          <option v-for="option in item.competitions" :key="option.code" :value="rosterKey(item.season, option.code)" :selected="item.season === season && option.code === competition">
            {{ option.display_title }}
          </option>
        </optgroup>
      </select>
    </label>
    <UButton type="submit" variant="outline">{{ $t('common.show') }}</UButton>
  </form>
</template>
