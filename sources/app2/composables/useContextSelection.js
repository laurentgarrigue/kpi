import { toRaw } from 'vue'
import { usePreferenceStore } from '~/stores/preferenceStore'
import { useEventStore } from '~/stores/eventStore'
import { useGroupStore } from '~/stores/groupStore'
import { useApi } from '~/composables/useApi'
import db from '~/utils/db'

/**
 * Selects the event or the group the application works on (what the event selector does by hand), so that a page
 * opened from a link — the share URLs `/event/{id}` and `/group/{season}/{code}`, or a game sheet linked from the
 * public website — leaves the other pages (games, rankings, teams) on the right perimeter.
 */
export const useContextSelection = () => {
  const preferenceStore = usePreferenceStore()
  const eventStore = useEventStore()
  const groupStore = useGroupStore()
  const { getApi } = useApi()

  // Another perimeter: the cached games and charts are not the ones of the new one
  const forgetCachedData = () => Promise.all([
    db.games.clear(),
    db.charts.clear(),
    preferenceStore.putItem('games_last_api_load', 0),
    preferenceStore.putItem('charts_last_api_load', 0)
  ])

  const fetchAllEvents = async () => {
    try {
      const [stdResponse, champResponse] = await Promise.all([getApi('/events/std'), getApi('/events/champ')])
      if (!stdResponse.ok || !champResponse.ok) {
        throw new Error('Failed to fetch events')
      }
      const allEvents = [
        ...(await stdResponse.json()).map(event => ({ ...event, id: parseInt(event.id), type: 'std' })),
        ...(await champResponse.json()).map(event => ({ ...event, id: parseInt(event.id), type: 'champ' }))
      ]
      await eventStore.clearAndUpdateEvents(allEvents)
      return true
    } catch (error) {
      console.error('Error fetching all events:', error)
      return false
    }
  }

  const fetchGroups = async (season) => {
    try {
      const result = await getApi(`/groups/${season}`)
      if (!result.ok) {
        throw new Error('Failed to fetch groups')
      }
      const data = await result.json()
      groupStore.selectSeason(season)
      groupStore.setSections(data.sections)
      return true
    } catch (error) {
      console.error('Error fetching groups:', error)
      return false
    }
  }

  /** @returns {Promise<boolean>} whether the event is now selected */
  const selectEvent = async (eventId) => {
    await preferenceStore.fetchItems()
    const lastEvent = preferenceStore.preferences.lastEvent
    if (lastEvent && lastEvent.id !== eventId) {
      await forgetCachedData()
    }
    if (!(await fetchAllEvents())) {
      return false
    }
    const selectedEvent = eventStore.getEventById(eventId)
    if (!selectedEvent) {
      return false
    }
    await preferenceStore.putItem('lastEvent', toRaw(selectedEvent))
    await preferenceStore.putItem('lastGroup', null)
    await preferenceStore.putItem('last_team', null)
    await preferenceStore.putItem('eventMode', selectedEvent.type === 'champ' ? 'champ' : 'std')
    return true
  }

  /** @returns {Promise<boolean>} whether the group is now selected */
  const selectGroup = async (season, groupCode) => {
    await preferenceStore.fetchItems()
    const lastGroup = preferenceStore.preferences.lastGroup
    if (lastGroup && (lastGroup.code !== groupCode || preferenceStore.preferences.lastSeason !== season)) {
      await forgetCachedData()
    }
    if (!(await fetchGroups(season))) {
      return false
    }
    const selectedGroup = groupStore.getGroupByCode(groupCode)
    if (!selectedGroup) {
      return false
    }
    await preferenceStore.putItem('lastGroup', toRaw(selectedGroup))
    await preferenceStore.putItem('lastSeason', season)
    await preferenceStore.putItem('lastEvent', null)
    await preferenceStore.putItem('eventMode', 'group')
    await preferenceStore.putItem('last_team', null)
    return true
  }

  /**
   * Keeps the current selection when it already lists the game, otherwise selects the event asked for, or the group of
   * the game (FEATURE_MATCH_MEDALS.md is not concerned: this is the navigation of the game sheet).
   *
   * @param {{ season: string, group: string|null, events: number[] }} context from the game sheet
   * @param {number|null} wantedEvent event of the link, when the visitor came from an event page
   */
  const selectForGame = async (context, wantedEvent = null) => {
    await preferenceStore.fetchItems()
    const { lastEvent, lastGroup, lastSeason } = preferenceStore.preferences
    if (wantedEvent !== null && context.events.includes(wantedEvent)) {
      return lastEvent?.id === wantedEvent || selectEvent(wantedEvent)
    }
    if (lastEvent && context.events.includes(Number(lastEvent.id))) {
      return true
    }
    if (lastGroup && context.group && lastGroup.code === context.group && lastSeason === context.season) {
      return true
    }
    return context.group ? selectGroup(context.season, context.group) : false
  }

  return { selectEvent, selectGroup, selectForGame }
}
