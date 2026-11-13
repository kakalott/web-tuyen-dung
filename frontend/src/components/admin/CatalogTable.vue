<template>
  <div class="catalog-table overflow-x-auto bg-white shadow rounded">
    <table class="w-full text-left border-collapse">
      <thead class="bg-gray-100">
        <tr>
          <th v-for="col in columns" :key="col.key" class="p-3 border-b">{{ col.label }}</th>
          <th class="p-3 border-b text-center">Trạng thái</th>
          <th class="p-3 border-b text-center">Thao tác</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="item in items" :key="item.id" class="border-b hover:bg-gray-50">
          <td v-for="col in columns" :key="col.key" class="p-3">
            {{ item[col.key] }}
          </td>
          <td class="p-3 text-center">
            <!-- Nút Toggle Active -->
            <button 
              @click="$emit('toggle', item)"
              :class="item.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-600'"
              class="px-3 py-1 rounded-full text-xs font-bold transition-colors cursor-pointer"
            >
              {{ item.is_active ? 'Đang hoạt động' : 'Đã ẩn' }}
            </button>
          </td>
          <td class="p-3 text-center space-x-2">
            <button @click="$emit('edit', item)" class="text-blue-600 hover:underline cursor-pointer">Sửa</button>
            <button v-if="hasAlias" @click="$emit('open-alias', item)" class="text-purple-600 hover:underline cursor-pointer">Alias</button>
          </td>
        </tr>
      </tbody>
    </table>
    <div v-if="items.length === 0" class="p-4 text-center text-gray-500">Không có dữ liệu.</div>
  </div>
</template>

<script setup>
defineProps({
  items: { type: Array, required: true },
  columns: { type: Array, required: true },
  hasAlias: { type: Boolean, default: false }
});
defineEmits(['edit', 'toggle', 'open-alias']);
</script>

<style scoped>
.catalog-table {
  width: 100%;
}
table th {
  font-size: 0.875rem;
  font-weight: 600;
  color: #374151;
  background-color: #f3f4f6;
}
table td {
  font-size: 0.875rem;
}
</style>
