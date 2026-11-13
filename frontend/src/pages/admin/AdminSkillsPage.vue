<template>
  <div class="admin-skills-page space-y-6">
    <div class="flex justify-between items-center">
      <h1 class="text-2xl font-bold text-gray-800">Quản lý Kỹ năng (Skills & Aliases)</h1>
      <button @click="openModal()" class="bg-blue-600 text-white px-4 py-2 rounded text-sm font-semibold cursor-pointer hover:bg-blue-700">+ Thêm Kỹ năng</button>
    </div>
    
    <!-- Thanh tìm kiếm -->
    <div class="bg-white p-4 shadow rounded flex gap-4">
      <input type="text" v-model="filters.search" @keyup.enter="loadSkills" placeholder="Tìm kiếm kỹ năng hoặc alias..." class="border p-2 rounded flex-1 text-sm" />
      <button @click="loadSkills" class="bg-gray-200 px-4 py-2 rounded text-sm font-semibold cursor-pointer hover:bg-gray-300">Tìm</button>
    </div>

    <div v-if="loading" class="text-gray-500 py-6 text-center">Đang tải danh sách kỹ năng...</div>
    <div v-else-if="pageError" class="text-red-500 bg-red-50 p-4 rounded">{{ pageError }}</div>
    
    <div v-else class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div class="col-span-2 space-y-4">
        <CatalogTable 
          :items="skills" 
          :columns="[{ key: 'name', label: 'Tên Kỹ năng chuẩn' }]" 
          has-alias
          @edit="openModal" 
          @toggle="toggleSkillActive"
          @open-alias="selectSkillForAlias"
        />
        
        <!-- Paginator -->
        <div v-if="totalItems > filters.page_size" class="flex justify-between items-center bg-white p-3 rounded shadow">
          <button 
            @click="changePage(filters.page - 1)" 
            :disabled="filters.page <= 1"
            class="px-3 py-1 bg-gray-100 rounded text-sm disabled:opacity-50 cursor-pointer"
          >
            ← Trước
          </button>
          <span class="text-sm text-gray-600">Trang {{ filters.page }} / {{ totalPages }}</span>
          <button 
            @click="changePage(filters.page + 1)" 
            :disabled="filters.page >= totalPages"
            class="px-3 py-1 bg-gray-100 rounded text-sm disabled:opacity-50 cursor-pointer"
          >
            Sau →
          </button>
        </div>
      </div>
      
      <!-- Cột Alias Editor -->
      <div v-if="selectedSkill">
        <AliasEditor 
           :skill="selectedSkill" 
           :aliases="selectedSkill.aliases || []" 
           :error="aliasError"
           :submitting="aliasSubmitting"
           @add="handleAddAlias" 
           @toggle="toggleAliasActive" 
        />
      </div>
      <div v-else class="bg-white p-6 shadow rounded border text-center text-gray-400 italic text-sm">
        Chọn nút "Alias" trên một kỹ năng chuẩn để quản lý các biến thể từ khóa.
      </div>
    </div>

    <!-- Modal Thêm/Sửa Skill -->
    <dialog :open="isModalOpen" class="p-6 bg-white rounded shadow-xl border w-96 fixed inset-0 m-auto z-50">
      <h3 class="font-bold text-lg mb-4 text-gray-800">{{ editForm.id ? 'Sửa Kỹ năng' : 'Thêm Kỹ năng' }}</h3>
      <div v-if="formError" class="bg-red-50 text-red-600 p-2 mb-4 text-sm rounded">{{ formError }}</div>
      
      <input type="text" v-model="editForm.name" placeholder="Tên kỹ năng" class="w-full border p-2 mb-4 rounded text-sm" @keyup.enter="submitSkill" />
      
      <div class="flex justify-end gap-2">
        <button @click="isModalOpen = false" class="px-4 py-2 bg-gray-200 rounded text-sm cursor-pointer hover:bg-gray-300">Hủy</button>
        <button @click="submitSkill" class="px-4 py-2 bg-blue-600 text-white rounded text-sm font-semibold cursor-pointer hover:bg-blue-700">Lưu</button>
      </div>
    </dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { adminApi } from '@/services/adminApi';
import CatalogTable from '@/components/admin/CatalogTable.vue';
import AliasEditor from '@/components/admin/AliasEditor.vue';

const skills = ref([]);
const filters = ref({ search: '', page: 1, page_size: 15 });
const totalItems = ref(0);
const loading = ref(true);
const pageError = ref('');

const totalPages = computed(() => Math.ceil(totalItems.value / filters.value.page_size) || 1);

// Quản lý Modal Skill
const isModalOpen = ref(false);
const editForm = ref({ id: null, name: '' });
const formError = ref('');

// Quản lý Alias
const selectedSkill = ref(null);
const aliasError = ref('');
const aliasSubmitting = ref(false);

const loadSkills = async () => {
  loading.value = true;
  pageError.value = '';
  try {
    const res = await adminApi.getSkills(filters.value); // API-CAT-01
    skills.value = res.data || res.items || [];
    totalItems.value = res.total ?? skills.value.length;
    
    // Refresh selectedSkill if currently open
    if (selectedSkill.value) {
      const refreshed = skills.value.find(s => s.id === selectedSkill.value.id);
      if (refreshed) selectedSkill.value = refreshed;
    }
  } catch (err) {
    pageError.value = 'Lỗi tải danh sách kỹ năng.';
  } finally {
    loading.value = false;
  }
};

const changePage = (p) => {
  if (p < 1 || p > totalPages.value) return;
  filters.value.page = p;
  loadSkills();
};

const openModal = (skill = null) => {
  formError.value = '';
  editForm.value = skill ? { id: skill.id, name: skill.name } : { id: null, name: '' };
  isModalOpen.value = true;
};

const submitSkill = async () => {
  if (!editForm.value.name.trim()) {
    formError.value = 'Vui lòng nhập tên kỹ năng.';
    return;
  }
  formError.value = '';
  try {
    if (editForm.value.id) {
      await adminApi.updateSkill(editForm.value.id, { name: editForm.value.name.trim() }); // API-CAT-03
    } else {
      await adminApi.addSkill({ name: editForm.value.name.trim() }); // API-CAT-02
    }
    isModalOpen.value = false;
    loadSkills();
  } catch (err) {
    // BR-CAT-01: Bắt lỗi 409 nếu trùng tên hoặc alias
    formError.value = err.status === 409 ? 'Tên kỹ năng bị trùng lặp trên hệ thống.' : (err.message || 'Có lỗi xảy ra.');
  }
};

const toggleSkillActive = async (skill) => {
  try {
    await adminApi.updateSkill(skill.id, { is_active: !skill.is_active }); // Đảo cờ, giữ nguyên ID
    loadSkills();
  } catch (err) {
    alert(err.message || 'Cập nhật trạng thái thất bại.');
  }
};

const selectSkillForAlias = (skill) => {
  selectedSkill.value = skill;
  aliasError.value = '';
};

const handleAddAlias = async (aliasName) => {
  aliasSubmitting.value = true;
  aliasError.value = '';
  try {
    await adminApi.addSkillAlias(selectedSkill.value.id, { alias: aliasName }); // API-CAT-04
    await loadSkills();
    selectedSkill.value = skills.value.find(s => s.id === selectedSkill.value.id); // Re-bind
  } catch (err) {
    aliasError.value = err.status === 409 ? 'Alias này đã bị trùng trên hệ thống.' : (err.message || 'Không thể thêm alias.');
  } finally {
    aliasSubmitting.value = false;
  }
};

const toggleAliasActive = async (alias) => {
  try {
    await adminApi.updateSkillAlias(alias.id, { is_active: !alias.is_active }); // API-CAT-05
    await loadSkills();
    selectedSkill.value = skills.value.find(s => s.id === selectedSkill.value.id);
  } catch (err) {
    alert(err.message || 'Cập nhật alias thất bại.');
  }
};

onMounted(loadSkills);
</script>
