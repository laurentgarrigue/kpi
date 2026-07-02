import { defineStore } from 'pinia'

export const useScrutineeringStore = defineStore('scrutineeringStore', {
  state: () => ({
    players: [],
    loading: false,
    error: null
  }),
  actions: {
    setPlayers(players) {
      this.players = players
    },
    clearPlayers() {
      this.players = []
    },
    updatePlayerEquipment(playerId, field, value) {
      const player = this.players.find(p => p.player_id === playerId)
      if (player) {
        player[field] = value
      }
    },
    updatePlayerComment(playerId, comment) {
      const player = this.players.find(p => p.player_id === playerId)
      if (player) {
        player.comment = comment
      }
    },
    // Reflect a composition change (number / status) locally.
    // Maps the API field names (numero/capitaine) to the player object keys (num/cap).
    updatePlayerComposition(playerId, changes) {
      const player = this.players.find(p => p.player_id === playerId)
      if (!player) return
      if ('numero' in changes) player.num = changes.numero
      if ('capitaine' in changes) player.cap = changes.capitaine
    }
  }
})
