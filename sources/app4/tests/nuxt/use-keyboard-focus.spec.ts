// useKeyboardFocus: the editable-field state is re-read every second (a removed focused element sends no focusout).
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import { defineComponent, h } from 'vue'
import { useKeyboardFocus } from '../../composables/useKeyboardFocus'
import { track } from './modal-helpers'

beforeEach(() => vi.useFakeTimers())
afterEach(() => vi.useRealTimers())

describe('useKeyboardFocus', () => {
  it('notices a focused field that left the DOM without focusout', async () => {
    let state!: ReturnType<typeof useKeyboardFocus>
    const Host = defineComponent({
      setup() { state = useKeyboardFocus(); return () => h('div') },
    })
    track(await mountSuspended(Host))

    const input = document.createElement('input')
    document.body.appendChild(input)
    input.focus()
    await vi.advanceTimersByTimeAsync(1100)
    expect(state.fieldFocused.value).toBe(true)

    input.remove()
    await vi.advanceTimersByTimeAsync(1100)
    expect(state.fieldFocused.value).toBe(false)
  })
})
