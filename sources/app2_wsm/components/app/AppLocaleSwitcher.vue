<template>
  <div class="relative">
    <select
      v-model="locale"
      class="bg-gray-800 text-white px-3 py-2 rounded border border-gray-700 focus:outline-none focus:border-green-500 transition"
    >
      <option value="en">English</option>
      <option value="fr">Français</option>
      <option value="es">Español</option>
    </select>
  </div>
</template>

<script setup>
const { locale } = useI18n()
</script>
