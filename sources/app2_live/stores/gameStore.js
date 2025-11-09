import { defineStore } from 'pinia'
import db from '~/utils/db'

export const useGameStore = defineStore('gameStore', {
  state: () => ({
    currentGame: null,
    gameId: null,
    score: {
      scoreA: 0,
      scoreB: 0,
      period: 'M1',
      timer: '00:00',
      possession: 0,
      penaltiesA: [],
      penaltiesB: []
    },
    teams: {
      teamA: {
        id: null,
        name: '',
        club: '',
        logo: ''
      },
      teamB: {
        id: null,
        name: '',
        club: '',
        logo: ''
      }
    },
    currentEvent: null, // Current event being displayed (goal, card, etc.)
    eventHistory: []
  }),
  actions: {
    async loadGame(gameData) {
      this.currentGame = gameData
      this.gameId = gameData.g_id

      // Update teams
      this.teams.teamA = {
        id: gameData.t_a_id,
        name: gameData.t_a_label,
        club: gameData.t_a_club,
        logo: gameData.t_a_logo
      }
      this.teams.teamB = {
        id: gameData.t_b_id,
        name: gameData.t_b_label,
        club: gameData.t_b_club,
        logo: gameData.t_b_logo
      }

      // Update score
      this.score.scoreA = parseInt(gameData.g_score_a) || 0
      this.score.scoreB = parseInt(gameData.g_score_b) || 0
      this.score.period = gameData.g_period || 'M1'

      // Cache in IndexedDB
      await db.games.put({
        id: gameData.g_id,
        eventId: gameData.d_id,
        timestamp: Date.now(),
        data: gameData
      })
    },
    updateScore(scoreData) {
      if (scoreData.scoreA !== undefined) this.score.scoreA = scoreData.scoreA
      if (scoreData.scoreB !== undefined) this.score.scoreB = scoreData.scoreB
      if (scoreData.period !== undefined) this.score.period = scoreData.period
      if (scoreData.timer !== undefined) this.score.timer = scoreData.timer
      if (scoreData.possession !== undefined) this.score.possession = scoreData.possession
      if (scoreData.penaltiesA !== undefined) this.score.penaltiesA = scoreData.penaltiesA
      if (scoreData.penaltiesB !== undefined) this.score.penaltiesB = scoreData.penaltiesB

      // Cache score
      db.scores.put({
        gameId: this.gameId,
        timestamp: Date.now(),
        score: { ...this.score }
      })
    },
    setCurrentEvent(event) {
      this.currentEvent = event
      if (event) {
        this.eventHistory.push({
          ...event,
          timestamp: Date.now()
        })
      }
    },
    clearCurrentEvent() {
      this.currentEvent = null
    },
    resetGame() {
      this.currentGame = null
      this.gameId = null
      this.score = {
        scoreA: 0,
        scoreB: 0,
        period: 'M1',
        timer: '00:00',
        possession: 0,
        penaltiesA: [],
        penaltiesB: []
      }
      this.currentEvent = null
      this.eventHistory = []
    }
  }
})
