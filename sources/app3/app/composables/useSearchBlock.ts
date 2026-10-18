// Open state of the search block, shared by the header magnifier and the layout (FEATURE_SEARCH.md § 2, SRC-06).
export const SEARCH_BLOCK_ID = 'search-block'
export const SEARCH_BLOCK_TOGGLE_ID = 'search-block-toggle'

export function useSearchBlock() {
  const open = useState('search-block-open', () => false)

  function toggle() {
    open.value = !open.value
  }

  function close() {
    open.value = false
  }

  return { open, toggle, close }
}
