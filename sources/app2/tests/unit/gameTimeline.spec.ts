import { describe, expect, it } from 'vitest'
import {
  buildHorizontalTimeline, formatEventTime, hasRecordedTimes, parseEventTime, type GameEvent,
} from '../../utils/gameTimeline'

const event = (overrides: Partial<GameEvent> = {}): GameEvent => ({
  e_id: 1, e_team: 'A', e_type: 'B', e_period: 'M1', e_time: '08:00', e_number: 7, e_name: 'DUPONT', e_firstname: 'Jean', ...overrides,
})

describe('event time', () => {
  it('parses seconds, mm:ss and hh:mm:ss; anything unusable is 0', () => {
    expect(parseEventTime(95)).toBe(95)
    expect(parseEventTime('95')).toBe(95)
    expect(parseEventTime('08:30')).toBe(510)
    expect(parseEventTime('00:08:30')).toBe(510)
    expect(parseEventTime('abc')).toBe(0)
    expect(parseEventTime(null)).toBe(0)
    expect(parseEventTime(undefined)).toBe(0)
  })

  it('formats as mm:ss, with a placeholder when there is no time', () => {
    expect(formatEventTime('08:05')).toBe('08:05')
    expect(formatEventTime(65)).toBe('01:05')
    expect(formatEventTime(null)).toBe('--:--')
  })
})

describe('recorded times', () => {
  it('a sheet where every time is the start of the period (or zero) holds none', () => {
    expect(hasRecordedTimes([event({ e_time: '10:00' }), event({ e_time: '00:00' })])).toBe(false)
    expect(hasRecordedTimes([event({ e_period: 'P1', e_time: '05:00' })])).toBe(false)
  })

  it('one real time is enough; shoot-out events never count', () => {
    expect(hasRecordedTimes([event({ e_time: '10:00' }), event({ e_time: '07:12' })])).toBe(true)
    expect(hasRecordedTimes([event({ e_period: 'TB', e_time: '03:00' })])).toBe(false)
  })
})

describe('horizontal timeline', () => {
  it('always draws the two regular periods, each half of the bar', () => {
    const { segments } = buildHorizontalTimeline([], null)
    expect(segments.map(s => s.period)).toEqual(['M1', 'M2'])
    expect(segments.map(s => s.width)).toEqual([50, 50])
    expect(segments[1]!.left).toBe(50)
  })

  it('draws overtime only once reached', () => {
    expect(buildHorizontalTimeline([event({ e_period: 'P1', e_time: '04:00' })], null).segments.map(s => s.period))
      .toEqual(['M1', 'M2', 'P1'])
    expect(buildHorizontalTimeline([], 'P2').segments.map(s => s.period)).toEqual(['M1', 'M2', 'P1', 'P2'])
  })

  it('places an event at its elapsed time (the chrono counts down)', () => {
    const { markers } = buildHorizontalTimeline([event({ e_time: '05:00' })], null)
    expect(markers[0]!.position).toBeCloseTo(25) // 5 min elapsed of 20
  })

  it('stretches a period to the whole minute above its largest recorded time', () => {
    const { segments } = buildHorizontalTimeline([event({ e_time: '12:30' })], null)
    expect(segments[0]!.width).toBeGreaterThan(50)
  })

  it('stacks close markers of the same team on separate lanes, teams counted apart', () => {
    const goals = [event({ e_id: 1, e_time: '08:00' }), event({ e_id: 2, e_time: '07:59' }), event({ e_id: 3, e_team: 'B', e_time: '08:00' })]
    const { markers, laneCount } = buildHorizontalTimeline(goals, null)
    expect(markers.filter(m => m.event.e_team === 'A').map(m => m.lane)).toEqual([0, 1])
    expect(laneCount).toEqual({ A: 2, B: 1 })
  })

  it('an event without period belongs to the first one', () => {
    expect(buildHorizontalTimeline([event({ e_period: null, e_time: '05:00' })], null).markers).toHaveLength(1)
  })
})
