import { describe, expect, it } from 'vitest'
import { resolveApi2BaseUrl } from '../app/utils/api2'

const urls = { internalUrl: 'http://kpi_api2', publicUrl: 'https://kayak-polo.info/api2' }

describe('resolveApi2BaseUrl (PLT-04)', () => {
  it('PLT-04: uses the internal URL on the server', () => {
    expect(resolveApi2BaseUrl({ ...urls, isServer: true })).toBe('http://kpi_api2')
  })

  it('PLT-04: uses the public URL in the browser', () => {
    expect(resolveApi2BaseUrl({ ...urls, isServer: false })).toBe('https://kayak-polo.info/api2')
  })

  it('falls back to the public URL on the server when no internal URL is configured', () => {
    expect(resolveApi2BaseUrl({ ...urls, internalUrl: '', isServer: true })).toBe('https://kayak-polo.info/api2')
  })

  it('removes trailing slashes so that paths can always start with "/"', () => {
    expect(resolveApi2BaseUrl({ internalUrl: '', publicUrl: 'https://x.test/api2/', isServer: false }))
      .toBe('https://x.test/api2')
  })
})
