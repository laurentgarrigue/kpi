import { computed } from 'vue'

// Navigation order of the swipe / arrow navigation
const NAVIGATION_ORDER = ['/', '/games', '/charts', '/team', '/about']

export const useNavigation = () => {
  const route = useRoute()

  const currentIndex = computed(() => {
    // Handle /team/:param case
    if (route.path.startsWith('/team/')) {
      return NAVIGATION_ORDER.indexOf('/team')
    }
    return NAVIGATION_ORDER.indexOf(route.path)
  })

  const previousPage = computed(() => {
    if (currentIndex.value <= 0) {
      return NAVIGATION_ORDER[NAVIGATION_ORDER.length - 1]
    }
    return NAVIGATION_ORDER[currentIndex.value - 1]
  })

  const nextPage = computed(() => {
    if (currentIndex.value >= NAVIGATION_ORDER.length - 1) {
      return NAVIGATION_ORDER[0]
    }
    return NAVIGATION_ORDER[currentIndex.value + 1]
  })

  return {
    previousPage,
    nextPage
  }
}
