<script setup lang="ts">
import type { Editor } from '@tiptap/vue-3'
import { TableKit } from '@tiptap/extension-table'
import { Youtube } from '@tiptap/extension-youtube'
import type { CmsMedia } from '~/types/cms'

// Rich text editor of articles, pages and forms (PAGE_CMS.md § 2, Q-P4-2: TipTap, through Nuxt UI's UEditor).
// The toolbar only offers what the api2 HTML filter keeps (ADM-CMS-03): h2–h4, bold, italic, underline, strike,
// lists, quote, link, image from the media library, table, YouTube video.
const html = defineModel<string>({ required: true })
const { t } = useI18n()

const extensions = [TableKit.configure({ table: { resizable: false } }), Youtube.configure({ nocookie: true, controls: true })]
const pickerOpen = ref(false)
const linkOpen = ref(false)
const videoOpen = ref(false)
const url = ref('')
let current: Editor | null = null

// Saved YouTube iframes come back without their TipTap wrapper: restore it so that the editor keeps them.
const content = computed({
  get: () => html.value.replace(/<iframe([^>]*youtube[^>]*)><\/iframe>/g, '<div data-youtube-video><iframe$1></iframe></div>'),
  set: (value: string) => { html.value = value },
})

type Command = { icon: string, label: string, run: (editor: Editor) => void, active?: (editor: Editor) => boolean }
const commands: Command[] = [
  { icon: 'heroicons:h2', label: 'cms.editor.h2', run: e => e.chain().focus().toggleHeading({ level: 2 }).run(), active: e => e.isActive('heading', { level: 2 }) },
  { icon: 'heroicons:h3', label: 'cms.editor.h3', run: e => e.chain().focus().toggleHeading({ level: 3 }).run(), active: e => e.isActive('heading', { level: 3 }) },
  { icon: 'heroicons:hashtag', label: 'cms.editor.h4', run: e => e.chain().focus().toggleHeading({ level: 4 }).run(), active: e => e.isActive('heading', { level: 4 }) },
  { icon: 'heroicons:bold', label: 'cms.editor.bold', run: e => e.chain().focus().toggleBold().run(), active: e => e.isActive('bold') },
  { icon: 'heroicons:italic', label: 'cms.editor.italic', run: e => e.chain().focus().toggleItalic().run(), active: e => e.isActive('italic') },
  { icon: 'heroicons:underline', label: 'cms.editor.underline', run: e => e.chain().focus().toggleUnderline().run(), active: e => e.isActive('underline') },
  { icon: 'heroicons:strikethrough', label: 'cms.editor.strike', run: e => e.chain().focus().toggleStrike().run(), active: e => e.isActive('strike') },
  { icon: 'heroicons:list-bullet', label: 'cms.editor.bullet_list', run: e => e.chain().focus().toggleBulletList().run(), active: e => e.isActive('bulletList') },
  { icon: 'heroicons:numbered-list', label: 'cms.editor.ordered_list', run: e => e.chain().focus().toggleOrderedList().run(), active: e => e.isActive('orderedList') },
  { icon: 'heroicons:chat-bubble-bottom-center-text', label: 'cms.editor.quote', run: e => e.chain().focus().toggleBlockquote().run(), active: e => e.isActive('blockquote') },
  { icon: 'heroicons:link', label: 'cms.editor.link', run: (e) => { current = e; url.value = e.getAttributes('link').href ?? ''; linkOpen.value = true }, active: e => e.isActive('link') },
  { icon: 'heroicons:photo', label: 'cms.editor.image', run: (e) => { current = e; pickerOpen.value = true } },
  { icon: 'heroicons:table-cells', label: 'cms.editor.table', run: e => e.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run() },
  { icon: 'heroicons:film', label: 'cms.editor.video', run: (e) => { current = e; url.value = ''; videoOpen.value = true } },
  { icon: 'heroicons:arrow-uturn-left', label: 'cms.editor.undo', run: e => e.chain().focus().undo().run() },
  { icon: 'heroicons:arrow-uturn-right', label: 'cms.editor.redo', run: e => e.chain().focus().redo().run() },
]

function insertImage(media: CmsMedia) {
  pickerOpen.value = false
  const src = media.url.split('?')[0] ?? media.url
  if (media.mime.startsWith('image/')) {
    current?.chain().focus().setImage({ src, alt: media.alt_fr }).run()
  } else {
    current?.chain().focus().insertContent(`<a href="${src}">${media.name}</a>`).run()
  }
}

function applyLink() {
  linkOpen.value = false
  const chain = current?.chain().focus().extendMarkRange('link')
  if (!url.value.trim()) {
    chain?.unsetLink().run()
    return
  }
  chain?.setLink({ href: url.value.trim() }).run()
}

function insertVideo() {
  videoOpen.value = false
  if (url.value.trim()) {
    current?.chain().focus().setYoutubeVideo({ src: url.value.trim() }).run()
  }
}
</script>

<template>
  <div class="rounded border border-header-300 dark:border-header-700" data-testid="rich-editor">
    <UEditor v-slot="{ editor }" v-model="content" content-type="html" :extensions="extensions" :mention="false" class="min-h-64">
      <div class="flex flex-wrap gap-0.5 border-b border-header-200 dark:border-header-700 p-1" role="toolbar" :aria-label="t('cms.editor.toolbar')">
        <UButton
          v-for="command in commands"
          :key="command.label"
          :icon="command.icon"
          size="xs"
          :variant="command.active?.(editor) ? 'soft' : 'ghost'"
          :color="command.active?.(editor) ? 'primary' : 'neutral'"
          :aria-label="t(command.label)"
          :title="t(command.label)"
          @click="command.run(editor)"
        />
      </div>
    </UEditor>
    <CmsMediaPicker :open="pickerOpen" @close="pickerOpen = false" @select="insertImage" />
    <AdminModal :open="linkOpen" :title="t('cms.editor.link')" @close="linkOpen = false">
      <form class="space-y-3" @submit.prevent="applyLink">
        <UFormField :label="t('cms.editor.url')" :help="t('cms.editor.link_help')">
          <UInput v-model="url" class="w-full" placeholder="https://… ou /competitions/…" autofocus />
        </UFormField>
        <UButton type="submit">{{ t('common.validate') }}</UButton>
      </form>
    </AdminModal>
    <AdminModal :open="videoOpen" :title="t('cms.editor.video')" @close="videoOpen = false">
      <form class="space-y-3" @submit.prevent="insertVideo">
        <UFormField :label="t('cms.editor.video_url')">
          <UInput v-model="url" class="w-full" placeholder="https://www.youtube.com/watch?v=…" autofocus />
        </UFormField>
        <UButton type="submit">{{ t('common.validate') }}</UButton>
      </form>
    </AdminModal>
  </div>
</template>
