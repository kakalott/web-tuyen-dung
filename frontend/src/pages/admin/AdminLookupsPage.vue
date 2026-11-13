<template>
  <div class="admin-lookups-page space-y-6">
    <!-- UI Chặn lỗi 404 cho Slug lạ -->
    <div v-if="!isValidCatalog" class="text-center p-12 bg-white rounded shadow border">
      <h1 class="text-5xl font-extrabold text-red-500">404</h1>
      <p class="text-gray-700 font-bold mt-4 text-lg">Danh mục không tồn tại</p>
      <p class="text-gray-500 text-sm mt-1">Slug <code>"{{ catalogSlug }}"</code> không thuộc danh sách danh mục được hỗ trợ.</p>
      <router-link to="/admin/dashboard" class="inline-block mt-4 bg-blue-600 text-white px-4 py-2 rounded text-sm font-semibold">
        Quay lại Dashboard
      </router-link>
    </div>
    
    <div v-else class="space-y-4">
      <div class="flex justify-between items-center">
        <div>
          <h1 class="text-2xl font-bold text-gray-800">Quản lý Danh mục: {{ catalogTitle }}</h1>
          <p class="text-xs text-gray-500">API Slug: <code>/api/v1/admin/catalog/{{ catalogSlug }}</code></p>
        </div>
        <button @click="openModal()" class="bg-blue-600 text-white px-4 py-2 rounded text-sm font-semibold cursor-pointer hover:bg-blue-700">+ Thêm mục</button>
      </div>
      
      <div v-if="loading" class="text-gray-500 py-6 text-center">Đang tải dữ liệu danh mục...</div>
      <div v-else-if="pageError" class="text-red-500 bg-red-50 p-4 rounded">{{ pageError }}</div>
      
      <div v-else class="space-y-4">
        <CatalogTable 
          :items="items" 
          :columns="[{ key: 'name', label: 'Tên danh mục' }]" 
          @edit="openModal" 
          @toggle="toggleActive"
        />

        <!-- Paginator -->
        <div v-if="totalItems > pageSize" class="flex justify-between items-center bg-white p-3 rounded shadow">
          <button 
            @click="changePage(currentPage - 1)" 
            :disabled="currentPage <= 1"
            class="px-3 py-1 bg-gray-100 rounded text-sm disabled:opacity-50 cursor-pointer"
          >
            ← Trước
          </button>
          <span class="text-sm text-gray-600">Trang {{ currentPage }} / {{ totalPages }}</span>
          <button 
            @click="changePage(currentPage + 1)" 
            :disabled="currentPage >= totalPages"
            class="px-3 py-1 bg-gray-100 rounded text-sm disabled:opacity-50 cursor-pointer"
          >
            Sau →
          </button>
        </div>
      </div>
      
      <!-- Modal Thêm/Sửa Lookup (API-CAT-08 / API-CAT-09) -->
      <dialog :open="isModalOpen" class="p-6 bg-white rounded shadow-xl border w-96 fixed inset-0 m-auto z-50">
        <h3 class="font-bold text-lg mb-4 text-gray-800">{{ editForm.id ? 'Sửa Mục Danh Mục' : 'Thêm Mục Mới' }}</h3>
        <div v-if="formError" class="bg-red-50 text-red-600 p-2 mb-4 text-sm rounded">{{ formError }}</div>
        
        <input type="text" v-model="editForm.name" placeholder="Tên mục..." class="w-full border p-2 mb-4 rounded text-sm" @keyup.enter="submitLookup" />
        
        <div class="flex justify-end gap-2">
          <button @click="isModalOpen = false" class="px-4 py-2 bg-gray-200 rounded text-sm cursor-pointer hover:bg-gray-300">Hủy</button>
          <button @click="submitLookup" class="px-4 py-2 bg-blue-600 text-white rounded text-sm font-semibold cursor-pointer hover:bg-blue-700">Lưu</button>
        </div>
      </dialog>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import { useRoute } from 'vue-router';
import { adminApi } from '@/services/adminApi';
import CatalogTable from '@/components/admin/CatalogTable.vue';

const route = useRoute();

// Whitelist Catalog (TC-CAT-03, TC-CAT-04)
const WHITELIST = {
  'job-groups': 'Nhóm ngành nghề',
  'employment-types': 'Loại hình việc làm',
  'work-modes': 'Hình thức làm việc',
  'seniority-levels': 'Cấp bậc kinh nghiệm'
};

const catalogSlug = computed(() => route.params.catalog);
const isValidCatalog = computed(() => Object.keys(WHITELIST).includes(catalogSlug.value));
const catalogTitle = computed(() => WHITELIST[catalogSlug.value]);

const items = ref([]);
const loading = ref(true);
const pageError = ref('');
const currentPage = ref(1);
const pageSize = ref(15);
const totalItems = ref(0);

const totalPages = computed(() => Math.ceil(totalItems.value / pageSize.value) || 1);

// Modal
const isModalOpen = ref(false);
const editForm = ref({ id: null, name: '' });
const formError = ref('');

const loadData = async () => {
  if (!isValidCatalog.value) return;
  loading.value = true;
  pageError.value = '';
  try {
    const res = await adminApi.getLookups(catalogSlug.value, {
      page: currentPage.value,
      page_size: pageSize.value
    }); // API-CAT-07
    items.value = res.data || res.items || [];
    totalItems.value = res.total ?? items.value.length;
  } catch (err) {
    pageError.value = err.message || 'Không thể tải danh mục.';
  } finally {
    loading.value = false;
  }
};

// Theo dõi thay đổi URL (do dùng route động) để tải lại data
watch(() => route.params.catalog, () => {
  currentPage.value = 1;
  loadData();
});
onMounted(loadData);

const changePage = (p) => {
  if (p < 1 || p > totalPages.value) return;
  currentPage.value = p;
  loadData();
};

const openModal = (item = null) => {
  formError.value = '';
  editForm.value = item ? { id: item.id, name: item.name } : { id: null, name: '' };
  isModalOpen.value = true;
};

const submitLookup = async () => {
  if (!editForm.value.name.trim()) {
    formError.value = 'Vui lòng nhập tên mục.';
    return;
  }
  formError.value = '';
  try {
    if (editForm.value.id) {
      await adminApi.updateLookup(catalogSlug.value, editForm.value.id, { name: editForm.value.name.trim() }); // API-CAT-09
    } else {
      await adminApi.addLookup(catalogSlug.value, { name: editForm.value.name.trim() }); // API-CAT-08
    }
    isModalOpen.value = false;
    loadData();
  } catch (err) {
    formError.value = err.status === 409 ? 'Tên mục danh mục đã tồn tại.' : (err.message || 'Có lỗi xảy ra.');
  }
};

const toggleActive = async (item) => {
  try {
    await adminApi.updateLookup(catalogSlug.value, item.id, { is_active: !item.is_active }); // API-CAT-09
    loadData();
  } catch (err) {
    alert(err.message || 'Cập nhật trạng thái thất bại.');
  }
};
</script>
