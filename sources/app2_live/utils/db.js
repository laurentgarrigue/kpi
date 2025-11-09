import Dexie from 'dexie'

const db = new Dexie('app2_live')

db.version(1).stores({
  preferences: '&id',
  connections: '&id', // WebSocket/STOMP connection configurations
  events: '&id', // Event data
  games: '&id, eventId, timestamp', // Game data cache
  scores: '&gameId, timestamp', // Live score cache
})

export default db
