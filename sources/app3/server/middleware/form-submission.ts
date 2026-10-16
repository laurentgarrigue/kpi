import { joinURL } from 'ufo'
import type { SubmissionOutcome, SubmissionResult } from '~~/shared/utils/form-submission'

declare module 'h3' {
  interface H3EventContext {
    /** Outcome of a form POST, rendered by the form page (pages/forms/[slug]/index.vue). */
    formSubmission?: SubmissionOutcome
  }
}

/**
 * A registration form posted without JavaScript (FRM-01): relays the answers to api2 with the visitor address (rate
 * limit per IP, FRM-05), then lets the page render the confirmation or the errors.
 */
export default defineEventHandler(async (event) => {
  const target = event.method === 'POST' ? formSubmissionTarget(getRequestURL(event).pathname) : null
  if (!target) {
    return
  }
  const config = useRuntimeConfig(event)
  const values = submissionBody(await readBody(event).catch(() => null))
  const visitor = getRequestHeader(event, 'x-forwarded-for') ?? getRequestIP(event)
  try {
    const result = await $fetch<SubmissionResult>(joinURL(config.api2InternalUrl || config.public.api2BaseUrl, 'forms', target.slug, 'submissions'), {
      method: 'POST',
      body: values,
      query: target.lang ? { lang: target.lang } : {},
      headers: visitor ? { 'X-Forwarded-For': visitor } : {},
    })
    event.context.formSubmission = { kind: 'success', result }
  } catch (error) {
    const { statusCode, data } = error as { statusCode?: number, data?: unknown }
    event.context.formSubmission = submissionFailure(statusCode, data, values)
  }
})
