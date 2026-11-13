<template>
  <div class="alias-editor bg-gray-50 p-4 rounded border">
    <h4 class="font-bold mb-2">Biến thể (Aliases) cho: {{ skill.name }}</h4>
    
    <form @submit.prevent="handleAddAlias" class="flex gap-2 mb-4">
      <input type="text" v-model="newAlias" placeholder="Nhập tên alias mới (VD: JS)" class="border p-2 flex-1 rounded text-sm bg-white" required />
      <button type="submit" class="bg-primary bg-blue-600 text-white px-4 py-2 rounded text-sm font-semibold cursor-pointer" :disabled="submitting">Thêm</button>
    </form>
    
    <div v-if="error" class="text-red-500 text-sm mb-2">{{ error }}</div>
    
    <div class="flex flex-wrap gap-2">
      <div v-for="alias in aliases" :key="alias.id" 
           class="flex items-center gap-2 px-3 py-1 rounded-full border text-xs"
           :class="alias.is_active ? 'bg-white border-purple-200' : 'bg-gray-200 text-gray-500 line-through'">
        <span>{{ alias.alias }}</span>
        <button @click="$emit('toggle', alias)" class="text-xs ml-2 hover:text-red-600 cursor-pointer" :title="alias.is_active ? 'Ẩn alias' : 'Bật alias'">
          {{ alias.is_active ? '✖' : '↺' }}
        </button>
      </div>
      <div v-if="!aliases || aliases.length === 0" class="text-gray-400 text-xs italic">
        Chưa có alias nào.
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';

const props = defineProps({
  skill: Object,
  aliases: Array,
  submitting: Boolean,
  error: String
});
const emit = defineEmits(['add', 'toggle']);

const newAlias = ref('');

const handleAddAlias = () => {
  if (!newAlias.value.trim()) return;
  emit('add', newAlias.value.trim());
  newAlias.value = '';
};
</script>
