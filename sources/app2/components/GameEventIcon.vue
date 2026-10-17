<template>
  <div v-if="type === 'B'" class="w-6 h-6 bg-bar rounded-full flex items-center justify-center">
    <div class="w-4 h-4 border-2 border-white rounded-full"/>
  </div>
  <div v-else-if="type === 'V'" class="w-5 h-7 bg-green-500 rounded-sm transform -rotate-12"/>
  <div v-else-if="type === 'J'" class="w-5 h-7 bg-yellow-400 rounded-sm transform -rotate-12"/>
  <div v-else-if="type === 'R'" class="relative w-5 h-7">
    <div class="absolute bg-yellow-400 w-5 h-7 rounded-sm transform -rotate-3 translate-x-1 translate-y-1"/>
    <div class="relative bg-red-500 rounded-sm transform -rotate-12 w-5 h-7"/>
  </div>
  <div v-else-if="type === 'D'" class="w-5 h-7 bg-red-600 rounded-sm transform -rotate-12 flex items-center justify-center text-white font-bold">E</div>
  <div v-else class="w-6 h-6 bg-gray-400 rounded-full flex items-center justify-center text-white text-xs">?</div>
</template>

<script setup>
defineProps({
  // B = goal, V = green card, J = yellow card, R = red card, D = definitive exclusion
  type: { type: String, required: true }
})
</script>
