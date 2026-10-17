<script setup lang="ts">
import { joinURL } from 'ufo'
import { ADMIN_PATH } from '~/utils/links'

// Site footer (SITE_LAYOUT.md § 2.4, LAY-04).
const { legacyBaseUrl, app2BaseUrl, version } = useRuntimeConfig().public
const adminUrl = joinURL(legacyBaseUrl, ADMIN_PATH)
const year = new Date().getFullYear()

const FFCK_URL = 'https://www.ffck.org'
const FACEBOOK_URL = 'https://www.facebook.com/ffckkp/'
const KIP_SPORT_URL = 'https://www.facebook.com/KIPsport'
</script>

<template>
  <footer class="bg-navy text-white">
    <div class="mx-auto grid max-w-7xl gap-8 px-4 py-10 sm:grid-cols-2 lg:grid-cols-4 lg:px-8">
      <section>
        <h2 class="mb-3 text-xl">{{ $t('footer.kayakPolo') }}</h2>
        <img
          src="/img/brand/cna-kayak-polo.png"
          :alt="$t('footer.cnaLogoAlt')"
          width="120"
          height="66"
          loading="lazy"
          class="mb-3 rounded bg-white p-2"
        >
        <p class="text-sm">{{ $t('site.tagline') }}</p>
        <a :href="FFCK_URL" class="text-sm underline">{{ $t('footer.ffckSite') }}</a>
      </section>
      <section>
        <h2 class="mb-3 text-xl">{{ $t('footer.follow') }}</h2>
        <ul class="space-y-2 text-sm">
          <li><SiteExternalLink :href="FACEBOOK_URL" new-tab class="underline">{{ $t('footer.facebook') }}</SiteExternalLink></li>
          <li><SiteExternalLink :href="app2BaseUrl" new-tab class="underline">{{ $t('footer.liveApp') }}</SiteExternalLink></li>
        </ul>
      </section>
      <section>
        <h2 class="mb-3 text-xl">{{ $t('footer.partner') }}</h2>
        <SiteExternalLink :href="KIP_SPORT_URL" new-tab class="inline-block rounded bg-white p-2">
          <img src="/img/kip-sport.png" :alt="$t('footer.kipSportAlt')" width="72" height="72" loading="lazy">
        </SiteExternalLink>
      </section>
      <section>
        <h2 class="mb-3 text-xl">{{ $t('footer.links') }}</h2>
        <ul class="space-y-2 text-sm">
          <li><a :href="legacyBaseUrl" class="underline">{{ $t('footer.legacySite') }}</a></li>
          <li><a :href="adminUrl" class="underline">{{ $t('footer.admin') }}</a></li>
        </ul>
      </section>
    </div>
    <div class="border-t border-white/20">
      <p class="mx-auto flex max-w-7xl flex-wrap justify-between gap-2 px-4 py-4 text-xs lg:px-8">
        <span data-testid="copyright">{{ $t('footer.copyright', { year }) }}</span>
        <span>{{ $t('footer.version', { version }) }}</span>
      </p>
    </div>
  </footer>
</template>
