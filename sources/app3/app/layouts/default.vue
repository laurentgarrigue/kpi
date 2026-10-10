<!-- General template (SITE_LAYOUT.md § 2, LAY-01) -->
<template>
  <div class="flex min-h-screen flex-col">
    <a
      href="#content"
      class="sr-only focus:not-sr-only focus:absolute focus:z-50 focus:bg-white focus:p-3 focus:text-kpi-blue-600"
    >{{ $t('a11y.skipToContent') }}</a>
    <SiteBetaBanner />
    <SiteHeader />
    <NavMain />
    <main id="content" class="mx-auto w-full max-w-7xl flex-1 px-4 py-8 lg:px-8">
      <slot />
    </main>
    <SiteFooter />
    <SiteScrollArrows />
  </div>
</template>
