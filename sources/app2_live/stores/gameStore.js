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
    async loadGame(gameData, gameId = null) {
      this.currentGame = gameData
      // Support multiple field names for game ID
      this.gameId = gameId || gameData.g_id || gameData.id_match || gameData.id

      // Update teams (handle missing fields gracefully)
      this.teams.teamA = {
        id: gameData.t_a_id || gameData.team_a_id || null,
        name: gameData.t_a_label || gameData.team_a_name || gameData.equipe1?.nom || '',
        club: gameData.t_a_club || gameData.team_a_club || gameData.equipe1?.club || '',
        logo: gameData.t_a_logo || gameData.team_a_logo || gameData.equipe1?.logo || ''
      }
      this.teams.teamB = {
        id: gameData.t_b_id || gameData.team_b_id || null,
        name: gameData.t_b_label || gameData.team_b_name || gameData.equipe2?.nom || '',
        club: gameData.t_b_club || gameData.team_b_club || gameData.equipe2?.club || '',
        logo: gameData.t_b_logo || gameData.team_b_logo || gameData.equipe2?.logo || ''
      }

      // Update score (handle missing fields)
      this.score.scoreA = parseInt(gameData.g_score_a || gameData.score_a || gameData.score1 || 0)
      this.score.scoreB = parseInt(gameData.g_score_b || gameData.score_b || gameData.score2 || 0)
      this.score.period = gameData.g_period || gameData.period || 'M1'

      // Cache in IndexedDB (try/catch to avoid errors if fields are missing)
      if (this.gameId) {
        try {
          await db.games.put({
            id: String(this.gameId), // Ensure it's a string
            eventId: gameData.d_id || gameData.event_id || gameData.id_event || null,
            timestamp: Date.now(),
            data: gameData
          })
        } catch (err) {
          console.warn('Could not cache game in IndexedDB:', err)
        }
      }
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
