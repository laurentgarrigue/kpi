<script setup lang="ts">
import { onClickOutside } from '@vueuse/core'
import type { ResolvedGroup, ResolvedLink } from '~/utils/navigation'

// Desktop sub-menu, opened on click, closed by Escape or a click outside (SITE_NAVIGATION.md § 4, NAV-07).
const props = defineProps<{ group: ResolvedGroup, isActive: (link: ResolvedLink) => boolean }>()
const groupActive = computed(() => props.group.children.some(props.isActive))

const open = ref(false)
const root = useTemplateRef<HTMLElement>('root')
const toggle = useTemplateRef<HTMLButtonElement>('toggle')
const panelId = computed(() => `nav-group-${props.group.id}`)

function close(returnFocus = false) {
  open.value = false
  if (returnFocus) {
    toggle.value?.focus()
  }
}

onClickOutside(root, () => close())
</script>

<template>
  <div ref="root" class="relative" @keydown.esc="close(true)">
    <button
      ref="toggle"
      type="button"
      :aria-expanded="open"
      :aria-controls="panelId"
      class="flex items-center gap-1 px-3 py-3 hover:bg-white/10"
      :class="{ 'underline decoration-2 underline-offset-8': groupActive }"
      @click="open = !open"
    >
      {{ $t(`nav.${group.id}`) }}
      <UIcon name="i-heroicons-chevron-down" class="size-4" aria-hidden="true" />
    </button>
    <ul
      v-show="open"
      :id="panelId"
      class="absolute left-0 z-20 min-w-64 rounded-b bg-white py-2 text-ink shadow-lg"
    >
      <li v-for="link in group.children" :key="link.id">
        <NavLink
          :link="link"
          :active="isActive(link)"
          class="px-4 py-2 hover:bg-kpi-blue-50"
          @click="close()"
        />
      </li>
    </ul>
  </div>
</template>
