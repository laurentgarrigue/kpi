import Dexie from 'dexie'

// Create database instance
const db = new Dexie('app2_wsm')

// Define schema versions
db.version(1).stores({
  preferences: '&id', // User preferences (language, event, etc.)
  user: '&id', // User data
  connections: '++id, eventId, pitch', // WebSocket connections
  events: '&id, name', // Events list
  messages: '++id, connectionId, timestamp', // WebSocket messages log
})

export default db
