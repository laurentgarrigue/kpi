import { computed } from 'vue'
import { useScrutineeringStore } from '~/stores/scrutineeringStore'
import { usePrefs } from '~/composables/usePrefs'

export const useScrutineering = () => {
  const store = useScrutineeringStore()
  const { prefs } = usePrefs()
  const { getApi, postApi } = useApi()

  const loadPlayers = async () => {
    if (!prefs.value?.lastEvent?.id || !prefs.value?.scr_team_id) {
      console.error('Missing event or team ID')
      return
    }

    store.loading = true
    store.error = null

    try {
      const response = await getApi(
        `/staff/${prefs.value.lastEvent.id}/team/${prefs.value.scr_team_id}/players`
      )

      if (!response.ok) {
        throw new Error('Failed to load players')
      }

      const data = await response.json()
      store.setPlayers(data)
    } catch (error) {
      console.error('Error loading players:', error)
      store.error = error.message
    } finally {
      store.loading = false
    }
  }

  const updatePlayer = async (playerId, equipment, currentValue) => {
    const max = equipment === 'paddle_count' ? 6 : 4
    const newValue = currentValue >= max ? 0 : currentValue + 1

    if (!prefs.value?.lastEvent?.id || !prefs.value?.scr_team_id) {
      console.error('Missing event or team ID')
      return
    }

    try {
      const response = await postApi(
        `/staff/${prefs.value.lastEvent.id}/team/${prefs.value.scr_team_id}/player/${playerId}/${equipment}/${newValue}`,
        {},
        'PUT'
      )

      if (!response.ok) {
        throw new Error('Failed to update player')
      }

      store.updatePlayerEquipment(playerId, equipment, newValue)
    } catch (error) {
      console.error('Error updating player:', error)
      if (error.message === 'Network Error') {
        console.log('Offline!')
      }
    }
  }

  const updateComment = async (playerId, comment) => {
    if (!prefs.value?.lastEvent?.id || !prefs.value?.scr_team_id) {
      console.error('Missing event or team ID')
      return
    }

    try {
      const response = await postApi(
        `/staff/${prefs.value.lastEvent.id}/team/${prefs.value.scr_team_id}/player/${playerId}/comment`,
        { comment },
        'PUT'
      )

      if (!response.ok) {
        throw new Error('Failed to update comment')
      }

      store.updatePlayerComment(playerId, comment)
    } catch (error) {
      console.error('Error updating comment:', error)
    }
  }

  // Composition adjustment (number / status) on the real team composition.
  // Returns { ok, error } so the caller can distinguish a lock (403) from other failures.
  const updateComposition = async (playerId, changes) => {
    if (!prefs.value?.lastEvent?.id || !prefs.value?.scr_team_id) {
      console.error('Missing event or team ID')
      return { ok: false, error: 'Missing event or team ID' }
    }

    try {
      const response = await postApi(
        `/staff/${prefs.value.lastEvent.id}/team/${prefs.value.scr_team_id}/composition/${playerId}`,
        changes,
        'PUT'
      )

      const data = await response.json().catch(() => ({}))
      if (!response.ok) {
        return { ok: false, error: data.error || 'Failed to update composition', status: response.status }
      }

      store.updatePlayerComposition(playerId, changes)
      return { ok: true }
    } catch (error) {
      console.error('Error updating composition:', error)
      return { ok: false, error: error.message }
    }
  }

  // Add an existing licensed player to the composition.
  // Returns { ok, error } so the caller can surface conflicts (already present, etc.).
  const addCompositionPlayer = async ({ matric, numero, capitaine }) => {
    if (!prefs.value?.lastEvent?.id || !prefs.value?.scr_team_id) {
      console.error('Missing event or team ID')
      return { ok: false, error: 'Missing event or team ID' }
    }

    try {
      const response = await postApi(
        `/staff/${prefs.value.lastEvent.id}/team/${prefs.value.scr_team_id}/player`,
        { matric, numero, capitaine },
        'POST'
      )

      const data = await response.json().catch(() => ({}))
      if (!response.ok) {
        return { ok: false, error: data.error || 'Failed to add player' }
      }

      await loadPlayers()
      return { ok: true }
    } catch (error) {
      console.error('Error adding player:', error)
      return { ok: false, error: error.message }
    }
  }

  // Autocomplete search for licensed players to add to the composition.
  const searchPlayers = async (q) => {
    if (!prefs.value?.lastEvent?.id || !prefs.value?.scr_team_id) return []
    if (!q || q.trim().length < 2) return []

    try {
      const response = await getApi(
        `/staff/${prefs.value.lastEvent.id}/team/${prefs.value.scr_team_id}/players/search?q=${encodeURIComponent(q.trim())}`
      )
      if (!response.ok) return []
      return await response.json()
    } catch (error) {
      console.error('Error searching players:', error)
      return []
    }
  }

  const resetTeam = async () => {
    if (!prefs.value?.lastEvent?.id || !prefs.value?.scr_team_id) {
      console.error('Missing event or team ID')
      return false
    }

    try {
      const response = await postApi(
        `/staff/${prefs.value.lastEvent.id}/team/${prefs.value.scr_team_id}/reset`,
        {},
        'DELETE'
      )

      if (!response.ok) {
        throw new Error('Failed to reset team')
      }

      await loadPlayers()
      return true
    } catch (error) {
      console.error('Error resetting team:', error)
      return false
    }
  }

  return {
    players: computed(() => store.players),
    loading: computed(() => store.loading),
    error: computed(() => store.error),
    loadPlayers,
    updatePlayer,
    updateComment,
    updateComposition,
    addCompositionPlayer,
    searchPlayers,
    resetTeam
  }
}