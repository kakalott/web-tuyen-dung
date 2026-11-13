<template>
  <div class="skill-selector">
    <div class="search-input-wrap">
      <input
        v-model="search"
        @input="onSearch"
        type="text"
        placeholder="Gõ để tìm kiếm kỹ năng..."
        class="input-search"
      />
    </div>

    <!-- Suggested skills dropdown -->
    <div v-if="suggestedSkills.length" class="suggestions-list">
      <button
        v-for="s in suggestedSkills"
        :key="s.id"
        type="button"
        @click="selectSkill(s)"
        :disabled="isSelected(s.id)"
        :class="['suggestion-item', { selected: isSelected(s.id) }]"
      >
        <span>{{ s.name }}</span>
        <span v-if="isSelected(s.id)" class="check">✓</span>
      </button>
    </div>

    <!-- Selected skills pills -->
    <div class="selected-pills">
      <span v-for="id in selected" :key="id" class="skill-pill">
        {{ getSkillName(id) }}
        <button type="button" @click="removeSkill(id)" class="remove-btn">&times;</button>
      </span>
      <span v-if="!selected.length" class="empty-hint">Chưa chọn kỹ năng nào</span>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { skillApi } from '@/services/skillApi';

const props = defineProps({
  selected: {
    type: Array,
    default: () => [],
  },
});

const emit = defineEmits(['update:selected']);

const search = ref('');
const suggestedSkills = ref([]);
const skillMap = ref({});
let timer = null;

const onSearch = () => {
  clearTimeout(timer);
  if (!search.value.trim()) {
    suggestedSkills.value = [];
    return;
  }
  timer = setTimeout(async () => {
    try {
      const res = await skillApi.searchSkills(search.value.trim());
      const items = res.data?.items || res.data || [];
      suggestedSkills.value = items;
      items.forEach((item) => {
        skillMap.value[item.id] = item.name;
      });
    } catch {
      suggestedSkills.value = [];
    }
  }, 250);
};

const isSelected = (id) => props.selected.includes(id);

const selectSkill = (skill) => {
  if (!isSelected(skill.id)) {
    skillMap.value[skill.id] = skill.name;
    emit('update:selected', [...props.selected, skill.id]);
  }
  search.value = '';
  suggestedSkills.value = [];
};

const removeSkill = (id) => {
  emit('update:selected', props.selected.filter((item) => item !== id));
};

const getSkillName = (id) => skillMap.value[id] || `Skill #${id}`;

onMounted(async () => {
  try {
    const res = await skillApi.searchSkills('');
    const items = res.data?.items || res.data || [];
    items.forEach((item) => {
      skillMap.value[item.id] = item.name;
    });
  } catch {
    // ignore
  }
});
</script>

<style scoped>
.skill-selector {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.input-search {
  width: 100%;
  padding: 0.5rem 0.75rem;
  border: 1px solid #cbd5e1;
  border-radius: 0.375rem;
  font-size: 0.875rem;
  outline: none;
}

.input-search:focus {
  border-color: #2563eb;
  box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.1);
}

.suggestions-list {
  max-height: 160px;
  overflow-y: auto;
  border: 1px solid #e2e8f0;
  border-radius: 0.375rem;
  background-color: #ffffff;
  display: flex;
  flex-direction: column;
}

.suggestion-item {
  padding: 0.4rem 0.75rem;
  text-align: left;
  border: none;
  background: transparent;
  cursor: pointer;
  display: flex;
  justify-content: space-between;
  font-size: 0.8125rem;
}

.suggestion-item:hover:not(:disabled) {
  background-color: #f1f5f9;
}

.suggestion-item.selected {
  color: #16a34a;
  background-color: #f0fdf4;
}

.selected-pills {
  display: flex;
  flex-wrap: wrap;
  gap: 0.375rem;
  min-height: 32px;
}

.skill-pill {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  background-color: #e0f2fe;
  color: #0369a1;
  padding: 0.2rem 0.5rem;
  border-radius: 0.25rem;
  font-size: 0.8125rem;
}

.remove-btn {
  background: transparent;
  border: none;
  color: #0284c7;
  cursor: pointer;
  font-size: 1rem;
  line-height: 1;
}

.empty-hint {
  font-size: 0.75rem;
  color: #94a3b8;
  padding: 0.25rem 0;
}
</style>
