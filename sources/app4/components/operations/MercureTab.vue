<script setup lang="ts">
import type { MercureConfig, MercurePublishResult, MercureReceivedMessage } from '~/types/operations'

const { t } = useI18n()
const api = useApi()
const toast = useToast()

// Fenêtre pendant laquelle les updates reçus après un connect() avec lastEventID
// sont considérés comme rejoués. Empirique : le hub sert l'historique depuis son
// disque local, c'est large pour ça, et court devant un rythme de publication
// manuel — le seul de ce banc de test.
const REPLAY_WINDOW_MS = 2000

// Config du hub (URL publique d'abonnement), lue depuis api2.
const config = ref<MercureConfig | null>(null)
const configLoading = ref(true)

// État de l'abonnement SSE.
type ConnectionState = 'disconnected' | 'connecting' | 'connected' | 'error'
const state = ref<ConnectionState>('disconnected')
const messages = ref<MercureReceivedMessage[]>([])
const publishing = ref(false)

// Formulaire de publication.
const topicSuffix = ref('ping')
const messageText = ref('Hello Mercure')
const isPrivate = ref(false)

// Reprise après coupure. Le navigateur gère déjà Last-Event-ID tout seul sur ses
// reconnexions automatiques, mais sans rien exposer : on ne peut ni choisir le
// point de reprise, ni voir ce qui est demandé. Le hub accepte le même mécanisme
// via le query param `lastEventID`, ce qui rend la reprise observable et testable
// sans toucher au réseau (Déconnecter/Connecter suffit à simuler la coupure).
const lastEventId = ref<string | null>(null)
const resumeFromId = ref(false)
// Saisissable : un id ancien montre jusqu'où l'historique remonte, un id bidon
// montre comment le hub réagit quand le point de reprise est introuvable.
const resumeIdInput = ref('')

// EventSource n'est pas réactif : hors de ref() pour éviter un proxy inutile.
let source: EventSource | null = null
// Marque les updates rejoués : le hub ne les distingue pas des updates live, or
// c'est toute l'information qu'on cherche à observer. Seul le premier lot reçu
// après un connect() avec lastEventID est un rattrapage.
let expectingReplay = false

const fullTopic = computed(() => `${config.value?.topicPrefix ?? 'test/'}${topicSuffix.value}`)

const stateColor = computed(() => ({
  connected: 'text-success-600 dark:text-success-300',
  connecting: 'text-warning-600 dark:text-warning-300',
  error: 'text-danger-600 dark:text-danger-300',
  disconnected: 'text-header-600 dark:text-header-300'
}[state.value]))

const loadConfig = async () => {
  configLoading.value = true
  try {
    config.value = await api.get<MercureConfig>('/admin/mercure/config')
  } catch {
    toast.add({
      title: t('common.error'),
      description: t('operations.mercure.error_config'),
      color: 'error',
      duration: 3000
    })
  } finally {
    configLoading.value = false
  }
}

const disconnect = () => {
  source?.close()
  source = null
  state.value = 'disconnected'
  expectingReplay = false
}

const connect = () => {
  if (!config.value?.publicUrl) return
  disconnect()

  state.value = 'connecting'

  // EventSource ne permet pas d'envoyer un header Authorization : en dev le hub
  // autorise l'anonyme (MERCURE_ANONYMOUS=1). Les updates publiés en "private"
  // ne seront donc PAS reçus ici — c'est justement ce que le test démontre.
  const url = new URL(config.value.publicUrl)
  url.searchParams.append('topic', fullTopic.value)

  // Demande explicite de rattrapage. Le hub rejoue tout ce qui a été publié
  // APRÈS cet id — d'où le fait qu'un id inconnu ne rejoue rien plutôt que tout.
  const resumeId = resumeIdInput.value.trim()
  expectingReplay = resumeFromId.value && resumeId !== ''
  if (expectingReplay) {
    url.searchParams.append('lastEventID', resumeId)
  }

  source = new EventSource(url.toString())

  source.onopen = () => {
    state.value = 'connected'
    // Le hub livre l'historique d'un bloc juste après l'ouverture, sans marqueur
    // de fin. Faute de signal, on borne le rattrapage dans le temps : passé ce
    // délai, ce qui arrive est du direct et ne doit plus être étiqueté "rejoué".
    if (expectingReplay) {
      setTimeout(() => { expectingReplay = false }, REPLAY_WINDOW_MS)
    }
  }

  source.onmessage = (event: MessageEvent<string>) => {
    // Mémorisé pour servir de point de reprise au prochain connect().
    if (event.lastEventId) lastEventId.value = event.lastEventId

    messages.value.unshift({
      id: event.lastEventId || crypto.randomUUID(),
      eventId: event.lastEventId || null,
      replayed: expectingReplay,
      topic: fullTopic.value,
      receivedAt: new Date().toLocaleTimeString(),
      raw: event.data
    })
    // Journal borné : au-delà, la page gonfle sans fin sur un topic bavard.
    if (messages.value.length > 50) messages.value.length = 50
  }

  source.onerror = () => {
    // EventSource retente tout seul ; on ne ferme pas, on signale juste l'état.
    state.value = 'error'
    // Les reconnexions automatiques repartent du Last-Event-ID géré par le
    // navigateur, pas du nôtre : ne plus étiqueter ce qui suit comme rattrapage.
    expectingReplay = false
  }
}

// Recopie le dernier id reçu dans le champ de reprise : le geste le plus courant
// est « je repars d'ici », inutile de le faire copier à la main.
const useLastEventId = () => {
  if (lastEventId.value) {
    resumeIdInput.value = lastEventId.value
    resumeFromId.value = true
  }
}

const publish = async () => {
  publishing.value = true
  try {
    const result = await api.post<MercurePublishResult>('/admin/mercure/publish', {
      topic: fullTopic.value,
      data: { message: messageText.value },
      private: isPrivate.value
    })
    toast.add({
      title: t('common.success'),
      description: t('operations.mercure.success_publish', { id: result.id }),
      color: 'success',
      duration: 3000
    })
  } catch {
    toast.add({
      title: t('common.error'),
      description: t('operations.mercure.error_publish'),
      color: 'error',
      duration: 3000
    })
  } finally {
    publishing.value = false
  }
}

const clearMessages = () => {
  messages.value = []
}

onMounted(loadConfig)
// Sans ça, l'abonnement survit au changement d'onglet (le composant est détruit
// par le v-if de la page, mais EventSource, lui, resterait ouvert).
onBeforeUnmount(disconnect)
</script>

<template>
  <div class="space-y-8">
    <section>
      <h2 class="text-lg font-semibold text-header-900 dark:text-header-50 mb-1">
        {{ t('operations.mercure.title') }}
      </h2>
      <p class="text-sm text-header-600 dark:text-header-300">
        {{ t('operations.mercure.description') }}
      </p>
    </section>

    <!-- État du hub -->
    <section class="bg-header-50 dark:bg-header-900 rounded-lg p-4">
      <div v-if="configLoading" class="flex items-center gap-2 text-header-600 dark:text-header-300">
        <UIcon name="i-heroicons-arrow-path" class="w-4 h-4 animate-spin" />
        {{ t('common.loading') }}
      </div>
      <div v-else-if="!config?.configured" class="flex items-start gap-3">
        <UIcon name="i-heroicons-exclamation-triangle" class="w-5 h-5 text-danger-600 dark:text-danger-300 mt-0.5 shrink-0" />
        <p class="text-sm text-danger-700 dark:text-danger-300">{{ t('operations.mercure.not_configured') }}</p>
      </div>
      <dl v-else class="space-y-2 text-sm">
        <div class="flex flex-col sm:flex-row sm:gap-2">
          <dt class="font-medium text-header-900 dark:text-header-50 sm:w-40 shrink-0">
            {{ t('operations.mercure.hub_url') }}
          </dt>
          <dd class="text-header-600 dark:text-header-300 break-all font-mono text-xs">{{ config.publicUrl }}</dd>
        </div>
        <div class="flex flex-col sm:flex-row sm:gap-2">
          <dt class="font-medium text-header-900 dark:text-header-50 sm:w-40 shrink-0">
            {{ t('operations.mercure.status') }}
          </dt>
          <dd :class="['font-medium', stateColor]">{{ t(`operations.mercure.state.${state}`) }}</dd>
        </div>
      </dl>
    </section>

    <!-- Abonnement -->
    <section>
      <h3 class="font-medium text-header-900 dark:text-header-50 mb-3">
        {{ t('operations.mercure.subscribe') }}
      </h3>
      <div class="flex flex-col sm:flex-row gap-3 sm:items-end">
        <div class="flex-1">
          <label for="mercure-topic" class="block text-sm font-medium text-header-900 dark:text-header-50 mb-1">
            {{ t('operations.mercure.topic') }}
          </label>
          <div class="flex items-center">
            <span class="px-3 py-2 rounded-l-lg border border-r-0 border-header-300 dark:border-header-700 bg-header-100 dark:bg-header-800 text-header-600 dark:text-header-300 text-sm font-mono">
              {{ config?.topicPrefix ?? 'test/' }}
            </span>
            <input
              id="mercure-topic"
              v-model="topicSuffix"
              type="text"
              class="flex-1 min-w-0 px-3 py-2 rounded-r-lg border border-header-300 dark:border-header-700 bg-white dark:bg-header-900 text-header-900 dark:text-header-50 text-sm focus:border-primary-500 focus:ring-primary-500"
            >
          </div>
        </div>
        <div class="flex gap-2">
          <button
            :disabled="!config?.configured || state === 'connected'"
            class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed"
            @click="connect"
          >
            {{ t('operations.mercure.connect') }}
          </button>
          <button
            :disabled="state === 'disconnected'"
            class="px-4 py-2 border border-header-300 dark:border-header-700 text-header-900 dark:text-header-50 rounded-lg hover:bg-header-100 dark:hover:bg-header-800 disabled:opacity-50 disabled:cursor-not-allowed"
            @click="disconnect"
          >
            {{ t('operations.mercure.disconnect') }}
          </button>
        </div>
      </div>
      <p class="mt-2 text-xs text-header-600 dark:text-header-300">
        {{ t('operations.mercure.topic_hint') }}
      </p>

      <!-- Reprise après coupure : rejoue l'historique depuis un id donné. -->
      <div class="mt-4 p-3 rounded-lg border border-header-200 dark:border-header-700 space-y-3">
        <label class="flex items-start gap-2">
          <input v-model="resumeFromId" type="checkbox" class="mt-1 rounded border-header-300 dark:border-header-700 text-primary-600 focus:ring-primary-500">
          <span class="text-sm text-header-900 dark:text-header-50">
            {{ t('operations.mercure.resume') }}
            <span class="block text-xs text-header-600 dark:text-header-300">{{ t('operations.mercure.resume_hint') }}</span>
          </span>
        </label>
        <div class="flex flex-col sm:flex-row gap-2 sm:items-center">
          <input
            id="mercure-resume-id"
            v-model="resumeIdInput"
            type="text"
            :disabled="!resumeFromId"
            :placeholder="t('operations.mercure.resume_placeholder')"
            class="flex-1 min-w-0 px-3 py-2 rounded-lg border border-header-300 dark:border-header-700 bg-white dark:bg-header-900 text-header-900 dark:text-header-50 text-xs font-mono focus:border-primary-500 focus:ring-primary-500 disabled:opacity-50"
          >
          <button
            :disabled="!lastEventId"
            class="px-3 py-2 text-sm border border-header-300 dark:border-header-700 text-header-900 dark:text-header-50 rounded-lg hover:bg-header-100 dark:hover:bg-header-800 disabled:opacity-50 disabled:cursor-not-allowed shrink-0"
            @click="useLastEventId"
          >
            {{ t('operations.mercure.use_last_id') }}
          </button>
        </div>
        <p v-if="lastEventId" class="text-xs text-header-600 dark:text-header-300 break-all">
          {{ t('operations.mercure.last_id') }} <span class="font-mono">{{ lastEventId }}</span>
        </p>
      </div>
    </section>

    <!-- Publication -->
    <section>
      <h3 class="font-medium text-header-900 dark:text-header-50 mb-3">
        {{ t('operations.mercure.publish') }}
      </h3>
      <div class="space-y-3">
        <div>
          <label for="mercure-message" class="block text-sm font-medium text-header-900 dark:text-header-50 mb-1">
            {{ t('operations.mercure.message') }}
          </label>
          <input
            id="mercure-message"
            v-model="messageText"
            type="text"
            class="w-full px-3 py-2 rounded-lg border border-header-300 dark:border-header-700 bg-white dark:bg-header-900 text-header-900 dark:text-header-50 text-sm focus:border-primary-500 focus:ring-primary-500"
          >
        </div>
        <label class="flex items-start gap-2">
          <input v-model="isPrivate" type="checkbox" class="mt-1 rounded border-header-300 dark:border-header-700 text-primary-600 focus:ring-primary-500">
          <span class="text-sm text-header-900 dark:text-header-50">
            {{ t('operations.mercure.private') }}
            <span class="block text-xs text-header-600 dark:text-header-300">{{ t('operations.mercure.private_hint') }}</span>
          </span>
        </label>
        <button
          :disabled="publishing || !config?.configured"
          class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
          @click="publish"
        >
          <UIcon v-if="publishing" name="i-heroicons-arrow-path" class="w-4 h-4 animate-spin" />
          {{ t('operations.mercure.publish_button') }}
        </button>
      </div>
    </section>

    <!-- Journal des messages reçus -->
    <section>
      <div class="flex items-center justify-between mb-3">
        <h3 class="font-medium text-header-900 dark:text-header-50">
          {{ t('operations.mercure.received', { count: messages.length }) }}
        </h3>
        <button
          v-if="messages.length > 0"
          class="text-sm text-header-600 dark:text-header-300 hover:text-header-900 dark:hover:text-header-50"
          @click="clearMessages"
        >
          {{ t('operations.mercure.clear') }}
        </button>
      </div>

      <p v-if="messages.length === 0" class="text-sm text-header-600 dark:text-header-300 italic">
        {{ t('operations.mercure.no_messages') }}
      </p>
      <ul v-else class="space-y-2">
        <li
          v-for="msg in messages"
          :key="msg.id"
          class="p-3 rounded-lg bg-header-50 dark:bg-header-900 border border-header-200 dark:border-header-700"
        >
          <div class="flex items-center justify-between gap-2 mb-1">
            <div class="flex items-center gap-2 min-w-0">
              <span class="text-xs font-mono text-primary-600 dark:text-primary-300 truncate">{{ msg.topic }}</span>
              <span
                v-if="msg.replayed"
                class="px-1.5 py-0.5 rounded text-xs font-medium bg-warning-100 dark:bg-warning-900 text-warning-700 dark:text-warning-300 shrink-0"
              >
                {{ t('operations.mercure.replayed') }}
              </span>
            </div>
            <span class="text-xs text-header-600 dark:text-header-300 shrink-0">{{ msg.receivedAt }}</span>
          </div>
          <pre class="text-xs text-header-900 dark:text-header-50 whitespace-pre-wrap break-all font-mono">{{ msg.raw }}</pre>
          <p v-if="msg.eventId" class="mt-1 text-xs text-header-600 dark:text-header-300 break-all font-mono">
            {{ msg.eventId }}
          </p>
        </li>
      </ul>
    </section>
  </div>
</template>
