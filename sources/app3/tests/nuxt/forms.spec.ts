import { beforeEach, describe, expect, it, vi } from 'vitest'
import { mockNuxtImport } from '@nuxt/test-utils/runtime'
import type { SubmissionOutcome } from '~~/shared/utils/form-submission'
import { fakeApi2 } from '../fixtures/api2'
import { expectNotFound, mountRoute } from './results-setup'

// Registration forms (FEATURE_FORMS.md § 3), on real api2 responses captured on SQL/fixtures. The POST relay itself
// (server/middleware/form-submission.ts) is covered by the e2e tests.
const { api2 } = vi.hoisted(() => ({ api2: vi.fn() }))
mockNuxtImport('useApi2', () => () => api2)
beforeEach(() => {
  api2.mockReset()
  api2.mockImplementation(fakeApi2)
  clearNuxtData()
  clearNuxtState()
})

const mountWithOutcome = (route: string, outcome: SubmissionOutcome) => {
  useState(`form-outcome:${route.split('/').pop()}`).value = outcome
  return mountRoute(route)
}

describe('form page (FEATURE_FORMS.md § 3)', () => {
  it('FRM-01: an open form is a plain HTML form posted to its page, with the anti-bot fields', async () => {
    const wrapper = await mountRoute('/forms/tournoi-test')
    expect(wrapper.find('h1').text()).toBe('Tournoi test')
    expect(wrapper.find('[data-testid="form-intro"]').text()).toBe('Inscrivez votre équipe.')
    expect(wrapper.find('[data-testid="form-state"]').text()).toBe('Inscriptions ouvertes.')
    const form = wrapper.find('form[data-testid="form"]')
    expect(form.attributes('method')).toBe('post')
    expect(form.attributes('action')).toBe('/forms/tournoi-test')
    expect(form.find('input[type="hidden"][name="_token"]').attributes('value')).toMatch(/^\d+\.[0-9a-f]{64}$/)
    expect(form.find('input[name="website"]').attributes('tabindex')).toBe('-1')
    expect(form.findAll('[data-field]').map(field => field.attributes('data-field'))).toEqual(['email', 'phone', 'club', 'team', 'players', 'category', 'competition'])
    expect(form.find('#field-email').attributes()).toMatchObject({ type: 'email', name: 'email', autocomplete: 'email' })
    expect(form.find('#field-phone').attributes('type')).toBe('tel')
    expect(form.find('#field-players').attributes()).toMatchObject({ type: 'number', min: '5', max: '10' })
    expect(form.find('label[for="field-email"]').text()).toBe('E-mail *')
    expect(form.findAll('#field-club option').map(option => option.attributes('value'))).toContain('C001')
    expect(form.find('[data-testid="form-privacy"]').exists()).toBe(false)
    expect(wrapper.find('[data-testid="form-privacy"]').text()).toContain('90 jours')
  })

  it('FRM-11: the competition field offers the competitions given by api2', async () => {
    const options = (await mountRoute('/forms/tournoi-test')).findAll('#field-competition option')
    expect(options.map(option => option.text())).toEqual(['Choisir…', 'Championnat Test (2999)', 'Championnat Logo (2999)', 'Championnat Sans Logo (2999)', 'Tournoi Non Chpt (2999)', 'Championnat Résultats (2999)', 'Rencontre Divers (2999)', 'Championnat En Attente (2999)'])
  })

  it('FRM-01: unknown or unpublished → 404; closed → message without form', async () => {
    expect(await expectNotFound('/forms/brouillon-form')).toBe(404)
    const closed = await mountRoute('/forms/clos')
    expect(closed.find('[data-testid="form-state"]').text()).toBe('Les inscriptions sont closes.')
    expect(closed.find('form').exists()).toBe(false)
  })

  it('FRM-02: server errors are rendered with a summary, linked to their field, the answers kept', async () => {
    const wrapper = await mountWithOutcome('/forms/tournoi-test', {
      kind: 'invalid',
      errors: { email: 'invalid', players: 'min' },
      values: { email: 'nope', players: '2', team: 'Alpha' },
    })
    const summary = wrapper.find('[data-testid="form-error-summary"]')
    expect(summary.attributes('role')).toBe('alert')
    expect(summary.text()).toContain('2 champs à corriger')
    expect(summary.findAll('a').map(link => link.attributes('href'))).toEqual(['#field-email', '#field-players'])
    const email = wrapper.find('#field-email')
    expect(email.attributes('aria-invalid')).toBe('true')
    expect(email.attributes('aria-describedby')).toBe('field-email-error')
    expect(email.attributes('value')).toBe('nope')
    expect(wrapper.find('#field-email-error').text()).toBe('Cette valeur n\'est pas valide.')
    expect(wrapper.find('#field-players-error').text()).toBe('La valeur est inférieure au minimum (5).')
    expect(wrapper.find('#field-team').attributes('value')).toBe('Alpha')
  })

  it('FRM-05: a refusal (rate limit…) is explained, the form stays', async () => {
    const wrapper = await mountWithOutcome('/forms/tournoi-test', { kind: 'rejected', reason: 'too_many_requests', values: {} })
    expect(wrapper.find('[data-testid="form-rejected"]').text()).toBe('Trop d\'envois en peu de temps : merci de patienter quelques minutes.')
    expect(wrapper.find('form').exists()).toBe(true)
  })

  it('FRM-03/FRM-07: the confirmation says confirmed or waiting with the reason, with the payment link', async () => {
    const confirmed = await mountWithOutcome('/forms/tournoi-test', { kind: 'success', result: { status: 'confirmed', reason: null, payment_url: null } })
    expect(confirmed.find('[data-testid="form-success"] h2').text()).toBe('Votre inscription est confirmée')
    expect(confirmed.find('form').exists()).toBe(false)
    expect(confirmed.find('[data-testid="form-payment"]').exists()).toBe(false)

    const waiting = await mountWithOutcome('/forms/stage-paiement', { kind: 'success', result: { status: 'waiting', reason: 'manual', payment_url: 'https://www.helloasso.com/test' } })
    expect(waiting.find('[data-testid="form-success"] h2').text()).toBe('Votre inscription est en attente')
    expect(waiting.find('[data-testid="form-waiting-reason"]').text()).toContain('L\'organisateur confirmera')
    const payment = waiting.find('[data-testid="form-payment"]')
    expect(payment.attributes('href')).toBe('https://www.helloasso.com/test')
    expect(payment.attributes('target')).toBe('_blank')
  })
})

describe('public list (FEATURE_FORMS.md § 3.5)', () => {
  it('FRM-06: confirmed then pending registrations, published columns only', async () => {
    const wrapper = await mountRoute('/forms/tournoi-test/entries')
    expect(wrapper.find('h1').text()).toBe('Inscrits — Tournoi test')
    const confirmed = wrapper.find('[data-testid="entries-confirmed"]')
    expect(confirmed.findAll('th').map(cell => cell.text())).toEqual(['#', 'Club', 'Équipe'])
    expect(confirmed.findAll('tbody td').map(cell => cell.text())).toEqual(['1', 'Club Alpha Lacville', 'Alpha'])
    expect(confirmed.find('h2').text()).toContain('1 inscription / 2')
    expect(wrapper.find('[data-testid="entries-waiting"]').text()).toContain('Aucune inscription.')
    expect(wrapper.text()).not.toMatch(/@|E-mail|Téléphone/)
  })

  it('FRM-06: a form without public list → 404', async () => {
    expect(await expectNotFound('/forms/clos/entries')).toBe(404)
  })
})

describe('linked content (NEWS-09, FRM-10)', () => {
  it('FRM-10/NEWS-09: the event page offers its open forms and its latest news', async () => {
    const wrapper = await mountRoute('/events/77/games')
    const forms = wrapper.find('[data-testid="linked-forms"]')
    expect(forms.find('a').attributes('href')).toBe('/forms/tournoi-test')
    expect(wrapper.findAll('[data-testid="linked-news"] [data-testid="news-card"] a').map(link => link.attributes('href'))).toEqual(['/news/finale-resultats'])
  })

  it('NEWS-09: the competition and club pages show their latest news; nothing when there is none', async () => {
    expect((await mountRoute('/competitions/2999/RCH/games')).find('[data-testid="linked-news"]').exists()).toBe(true)
    expect((await mountRoute('/clubs/C001')).find('[data-testid="linked-news"] h2').text()).toBe('Actualités')
    expect((await mountRoute('/competitions/2999/RCP/games')).find('[data-testid="linked-news"]').exists()).toBe(false)
  })
})
