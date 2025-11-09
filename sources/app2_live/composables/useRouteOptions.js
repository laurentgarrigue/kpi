import { ref, computed } from 'vue'
import { useI18n } from 'vue-i18n'

const allowedDisplays = ['main', 'match', 'score']
const allowedStyles = ['default', 'thury2014', 'saintomer2017', 'welland2018', 'saintomer2022', 'saintomer2022b']
const allowedLangs = ['en', 'fr']
const allowedZones = ['club', 'inter']
const allowedModes = ['full', 'only', 'events', 'static']

/**
 * Route options composable
 * Handles URL-based options for display customization
 */
export const useRouteOptions = () => {
  const route = useRoute()
  const runtimeConfig = useRuntimeConfig()
  const { locale } = useI18n()
  const backendBaseUrl = runtimeConfig.public.backendBaseUrl

  const css = ref('default')
  const display = ref('main')
  const mode = ref('full')
  const zone = ref('inter')
  const version = ref('2.0.0')

  const options = computed(() => {
    return route.params.options || []
  })

  /**
   * Load external CSS file
   * @param {string} cssName - CSS file name
   */
  const loadCss = (cssName) => {
    const element = document.createElement('link')
    element.setAttribute('rel', 'stylesheet')
    element.setAttribute('type', 'text/css')
    element.setAttribute('href', `${backendBaseUrl}/live/css/${cssName}.css?${version.value}`)
    element.setAttribute('data-css-name', cssName)
    document.getElementsByTagName('head')[0].appendChild(element)
  }

  /**
   * Remove external CSS file
   * @param {string} oldCss - CSS file name to remove
   */
  const trashCss = (oldCss) => {
    const element = document.querySelector(`link[data-css-name="${oldCss}"]`)
    if (element !== null) {
      element.remove()
    }
  }

  /**
   * Parse and apply route options
   */
  const checkOptions = () => {
    const optionsArray = Array.isArray(options.value) ? options.value : [options.value]

    optionsArray.forEach(option => {
      if (!option) return

      // Display mode
      if (allowedDisplays.includes(option) && option !== display.value) {
        display.value = option
        if (option === 'score') {
          loadCss('score')
        }
      }

      // CSS style
      if (allowedStyles.includes(option) && option !== css.value) {
        const oldCss = css.value
        css.value = option
        loadCss(option)
        trashCss(oldCss)
      }

      // Language
      if (allowedLangs.includes(option) && option !== locale.value) {
        locale.value = option
      }

      // Zone (club/inter)
      if (allowedZones.includes(option) && option !== zone.value) {
        zone.value = option
      }

      // Mode (full/only/events/static)
      if (allowedModes.includes(option) && option !== mode.value) {
        mode.value = option
      }
    })
  }

  return {
    css,
    display,
    mode,
    zone,
    options,
    version,
    checkOptions,
    loadCss,
    trashCss,
    allowedDisplays,
    allowedStyles,
    allowedZones,
    allowedModes
  }
}
