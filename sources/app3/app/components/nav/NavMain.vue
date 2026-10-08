<script setup lang="ts">
import { isMenuGroup } from '~/utils/navigation'

// Main navigation: horizontal bar from lg, collapsible panel below (SITE_NAVIGATION.md § 4).
const { items, isActive } = useMainMenu()
const route = useRoute()

const mobileOpen = ref(false)
watch(() => route.fullPath, () => {
  mobileOpen.value = false
})
</script>

<template>
  <nav :aria-label="$t('a11y.mainNav')" class="bg-navy text-white" @keydown.esc="mobileOpen = false">
    <div class="mx-auto max-w-7xl px-4 lg:px-8">
      <!-- Mobile toggle (NAV-08) -->
      <button
        type="button"
        class="flex items-center gap-2 py-3 font-semibold lg:hidden"
        :aria-expanded="mobileOpen"
        aria-controls="main-nav-mobile"
        data-testid="mobile-menu-toggle"
        @click="mobileOpen = !mobileOpen"
      >
        <UIcon :name="mobileOpen ? 'i-heroicons-x-mark' : 'i-heroicons-bars-3'" class="size-6" aria-hidden="true" />
        {{ $t('nav.menu') }}
      </button>

      <!-- Desktop bar -->
      <ul class="hidden items-center gap-1 lg:flex" data-testid="main-nav-desktop">
        <li v-for="item in items" :key="item.id">
          <NavGroup v-if="isMenuGroup(item)" :group="item" :is-active="isActive" />
          <NavLink
            v-else
            :link="item"
            :active="isActive(item)"
            class="px-3 py-3 hover:bg-white/10"
            :class="{ 'underline decoration-2 underline-offset-8': isActive(item) }"
          />
        </li>
      </ul>

      <!-- Mobile panel -->
      <ul v-show="mobileOpen" id="main-nav-mobile" class="flex flex-col pb-3 lg:hidden" data-testid="main-nav-mobile">
        <li v-for="item in items" :key="item.id" class="border-t border-white/20">
          <details v-if="isMenuGroup(item)" class="group">
            <summary class="flex cursor-pointer items-center justify-between py-3 font-semibold">
              {{ $t(`nav.${item.id}`) }}
              <UIcon name="i-heroicons-chevron-down" class="size-4 group-open:rotate-180" aria-hidden="true" />
            </summary>
            <ul class="pb-2 pl-4">
              <li v-for="link in item.children" :key="link.id">
                <NavLink :link="link" :active="isActive(link)" class="py-2" />
              </li>
            </ul>
          </details>
          <NavLink v-else :link="item" :active="isActive(item)" class="py-3 font-semibold" />
        </li>
      </ul>
    </div>
  </nav>
</template>
