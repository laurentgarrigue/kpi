import type { ResultsGame } from '../../app/utils/results/types'

/** A game with sensible defaults; override what the test is about. */
export function game(overrides: Partial<ResultsGame> = {}): ResultsGame {
  return {
    c_code: 'N1H', c_season: '2026', c_label: 'Poule A',
    d_id: 1, d_phase: 'Journée 1', d_level: 1, d_place: 'Saint-Omer', d_label: 'J1',
    g_id: 1, g_number: 1, g_date: '2026-06-14', g_time: '10:00', g_pitch: '1', g_code: 'M1',
    g_validation: '', g_status: 'ATT', g_period: null,
    g_score_a: null, g_score_b: null, g_coef_a: 1, g_coef_b: 1,
    t_a_id: 10, t_b_id: 11, t_a_label: 'Acigné', t_b_label: 'Thury', t_a_number: 1, t_b_number: 2,
    t_a_logo: null, t_b_logo: null,
    ...overrides,
  }
}
