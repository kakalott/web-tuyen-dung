<template>
  <div class="skills-page">
    <h1 class="page-title">Quản lý Kỹ năng</h1>

    <div v-if="loading" class="state-panel loading">Đang tải kỹ năng...</div>
    <div v-else-if="error" class="state-panel error">{{ error }}</div>

    <div v-else>
      <!-- Tìm kiếm & Chọn kỹ năng -->
      <section class="card">
        <h3 class="card-title">Thêm kỹ năng từ danh mục</h3>
        <div class="search-box">
          <input
            v-model="searchQuery"
            @input="handleSearch"
            type="text"
            placeholder="Gõ tên kỹ năng để tìm (ví dụ: Laravel, Python, Vue...)"
            class="search-input"
          />
        </div>
        <div v-if="searchResults.length" class="search-results">
          <button
            v-for="skill in searchResults"
            :key="skill.id"
            @click="addSkill(skill)"
            :disabled="selectedIds.has(skill.id)"
            class="search-result-item"
            :class="{ selected: selectedIds.has(skill.id) }"
          >
            {{ skill.name }}
            <span v-if="selectedIds.has(skill.id)" class="check">✓</span>
          </button>
        </div>
        <p v-else-if="searchQuery.length >= 2 && !searching" class="empty-hint">
          Không tìm thấy kỹ năng phù hợp.
        </p>
      </section>

      <!-- Kỹ năng đã chọn -->
      <section class="card" style="margin-top: 1.25rem;">
        <h3 class="card-title">Kỹ năng đã chọn ({{ selectedSkills.length }})</h3>
        <div v-if="!selectedSkills.length" class="empty-hint">
          Chưa chọn kỹ năng nào. Tìm kiếm và chọn từ danh mục ở trên.
        </div>
        <div v-else class="selected-tags">
          <span v-for="skill in selectedSkills" :key="skill.id" class="skill-tag">
            {{ skill.name }}
            <button @click="removeSkill(skill.id)" class="tag-remove">&times;</button>
          </span>
        </div>
        <div v-if="saveMsg" class="success-msg" style="margin-top: 0.75rem;">{{ saveMsg }}</div>
        <button
          @click="handleSave"
          :disabled="saving"
          class="btn-primary"
          style="margin-top: 1rem;"
        >
          {{ saving ? 'Đang lưu...' : 'Lưu kỹ năng' }}
        </button>
      </section>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { skillApi } from '@/services/skillApi';

const selectedSkills = ref([]);
const selectedIds = computed(() => new Set(selectedSkills.value.map((s) => s.id)));
const searchQuery = ref('');
const searchResults = ref([]);
const searching = ref(false);
const loading = ref(true);
const saving = ref(false);
const error = ref('');
const saveMsg = ref('');

let searchTimeout = null;

onMounted(async () => {
  try {
    const res = await skillApi.getMySkills();
    selectedSkills.value = res.data || [];
  } catch (err) {
    error.value = err.message || 'Không thể tải kỹ năng.';
  } finally {
    loading.value = false;
  }
});

const handleSearch = () => {
  clearTimeout(searchTimeout);
  if (searchQuery.value.length < 2) {
    searchResults.value = [];
    return;
  }
  searching.value = true;
  searchTimeout = setTimeout(async () => {
    try {
      const res = await skillApi.searchSkills(searchQuery.value);
      searchResults.value = res.data?.items || res.data || [];
    } catch {
      searchResults.value = [];
    } finally {
      searching.value = false;
    }
  }, 300);
};

const addSkill = (skill) => {
  if (!selectedIds.value.has(skill.id)) {
    selectedSkills.value.push(skill);
  }
};

const removeSkill = (id) => {
  selectedSkills.value = selectedSkills.value.filter((s) => s.id !== id);
};

const handleSave = async () => {
  saving.value = true;
  saveMsg.value = '';
  try {
    await skillApi.syncSkills(selectedSkills.value.map((s) => s.id));
    saveMsg.value = 'Đã lưu thành công!';
    setTimeout(() => (saveMsg.value = ''), 3000);
  } catch (err) {
    alert(err.message || 'Lưu kỹ năng thất bại.');
  } finally {
    saving.value = false;
  }
};
</script>

<style scoped>
.skills-page { max-width: 700px; }
.page-title { font-size: 1.5rem; font-weight: 700; color: #0f172a; margin-bottom: 1.5rem; }
.card { background: #fff; border: 1px solid #e2e8f0; border-radius: 0.5rem; padding: 1.25rem; }
.card-title { font-size: 1rem; font-weight: 600; color: #1e293b; margin-bottom: 0.75rem; }
.search-box { margin-bottom: 0.75rem; }
.search-input { width: 100%; padding: 0.625rem 0.875rem; border: 1px solid #cbd5e1; border-radius: 0.375rem; font-size: 0.875rem; outline: none; }
.search-input:focus { border-color: #2563eb; box-shadow: 0 0 0 2px rgba(37,99,235,0.1); }
.search-results { display: flex; flex-wrap: wrap; gap: 0.5rem; }
.search-result-item { background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 0.375rem; padding: 0.375rem 0.75rem; cursor: pointer; font-size: 0.8125rem; transition: background 0.15s; }
.search-result-item:hover:not(:disabled) { background: #dbeafe; border-color: #93c5fd; }
.search-result-item.selected { background: #dcfce7; border-color: #86efac; cursor: default; }
.search-result-item:disabled { opacity: 0.7; cursor: default; }
.check { color: #16a34a; margin-left: 0.25rem; }
.selected-tags { display: flex; flex-wrap: wrap; gap: 0.5rem; }
.skill-tag { display: inline-flex; align-items: center; gap: 0.375rem; background: #dbeafe; color: #1e40af; padding: 0.25rem 0.625rem; border-radius: 9999px; font-size: 0.8125rem; }
.tag-remove { background: none; border: none; color: #3b82f6; cursor: pointer; font-size: 1rem; line-height: 1; padding: 0; }
.btn-primary { background: #2563eb; color: #fff; border: none; padding: 0.5rem 1rem; border-radius: 0.375rem; cursor: pointer; font-weight: 500; font-size: 0.875rem; }
.btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
.empty-hint { color: #94a3b8; font-size: 0.8125rem; padding: 0.5rem 0; }
.success-msg { background: #f0fdf4; color: #16a34a; padding: 0.5rem 0.75rem; border-radius: 0.375rem; font-size: 0.875rem; border: 1px solid #bbf7d0; }
.state-panel { padding: 2rem; text-align: center; border-radius: 0.5rem; }
.state-panel.loading { color: #2563eb; background: #eff6ff; }
.state-panel.error { color: #b91c1c; background: #fef2f2; border: 1px solid #fecaca; }
</style>
