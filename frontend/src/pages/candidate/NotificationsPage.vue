<template>
  <div class="notifications-page">
    <div class="page-header">
      <h1 class="page-title">Thông báo</h1>
      <button
        v-if="notifications.length"
        @click="handleMarkAllRead"
        :disabled="markingAll"
        class="btn-mark-all"
      >
        {{ markingAll ? 'Đang xử lý...' : 'Đánh dấu tất cả đã đọc' }}
      </button>
    </div>

    <!-- Filter -->
    <div class="filter-row">
      <button
        @click="filterMode = 'all'"
        :class="['filter-btn', { active: filterMode === 'all' }]"
      >Tất cả</button>
      <button
        @click="filterMode = 'unread'"
        :class="['filter-btn', { active: filterMode === 'unread' }]"
      >Chưa đọc</button>
      <button
        @click="filterMode = 'read'"
        :class="['filter-btn', { active: filterMode === 'read' }]"
      >Đã đọc</button>
    </div>

    <div v-if="loading" class="state-panel loading">Đang tải thông báo...</div>
    <div v-else-if="error" class="state-panel error">{{ error }}</div>
    <div v-else-if="!filteredNotifications.length" class="state-panel empty">
      {{ filterMode === 'unread' ? 'Không có thông báo chưa đọc.' : 'Chưa có thông báo nào.' }}
    </div>

    <div v-else class="noti-list">
      <div
        v-for="noti in filteredNotifications"
        :key="noti.id"
        class="noti-card"
        :class="{ unread: !noti.is_read }"
        @click="handleClick(noti)"
      >
        <div class="noti-content">
          <div class="noti-event">{{ eventLabel(noti.event_type) }}</div>
          <div class="noti-body">{{ noti.content }}</div>
          <div class="noti-time text-sm text-muted">{{ formatDate(noti.created_at) }}</div>
        </div>
        <div v-if="!noti.is_read" class="unread-dot" title="Chưa đọc"></div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/authStore';
import { notificationApi } from '@/services/notificationApi';
import { resolveNotificationRoute } from '@/utils/notificationRouter';

const router = useRouter();
const authStore = useAuthStore();
const notifications = ref([]);
const loading = ref(true);
const error = ref('');
const filterMode = ref('all');
const markingAll = ref(false);

const filteredNotifications = computed(() => {
  if (filterMode.value === 'unread') return notifications.value.filter((n) => !n.is_read);
  if (filterMode.value === 'read') return notifications.value.filter((n) => n.is_read);
  return notifications.value;
});

const loadNotifications = async () => {
  loading.value = true;
  error.value = '';
  try {
    const params = filterMode.value === 'unread' ? 'is_read=0' : filterMode.value === 'read' ? 'is_read=1' : '';
    const res = await notificationApi.getNotifications(params);
    notifications.value = res.data || [];
  } catch (err) {
    error.value = err.message || 'Không thể tải thông báo.';
  } finally {
    loading.value = false;
  }
};

onMounted(loadNotifications);
watch(filterMode, loadNotifications);

const handleClick = async (noti) => {
  // Đánh dấu đã đọc nếu chưa đọc
  if (!noti.is_read) {
    try {
      await notificationApi.markRead(noti.id);
      noti.is_read = true;
      noti.read_at = new Date().toISOString();
    } catch {
      // ignore
    }
  }

  // Điều hướng an toàn dựa trên related_type qua Whitelist
  const targetRoute = resolveNotificationRoute(noti, authStore.user?.role || 'candidate');
  if (targetRoute) {
    router.push(targetRoute);
  }
};

const handleMarkAllRead = async () => {
  markingAll.value = true;
  try {
    await notificationApi.markAllRead();
    notifications.value.forEach((n) => {
      n.is_read = true;
      n.read_at = n.read_at || new Date().toISOString();
    });
  } catch (err) {
    alert(err.message || 'Thao tác thất bại.');
  } finally {
    markingAll.value = false;
  }
};

const eventLabel = (type) => {
  const map = {
    'job.approved': '✅ Tin tuyển dụng được duyệt',
    'job.rejected': '❌ Tin tuyển dụng bị từ chối',
    'job.hidden': '🔒 Tin tuyển dụng bị ẩn',
    'application.created': '📨 Nhận đơn ứng tuyển mới',
    'application.status_changed': '🔄 Trạng thái đơn thay đổi',
    'application.withdrawn': '↩️ Đơn ứng tuyển đã rút',
    'interview.created': '📅 Lịch phỏng vấn mới',
    'interview.updated': '✏️ Cập nhật lịch phỏng vấn',
    'interview.cancelled': '🚫 Hủy lịch phỏng vấn',
  };
  return map[type] || type;
};

const formatDate = (d) => (d ? new Date(d).toLocaleString('vi-VN') : '');
</script>

<style scoped>
.notifications-page { max-width: 700px; }
.page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
.page-title { font-size: 1.5rem; font-weight: 700; color: #0f172a; margin: 0; }
.btn-mark-all { background: transparent; color: #2563eb; border: 1px solid #2563eb; padding: 0.375rem 0.75rem; border-radius: 0.375rem; cursor: pointer; font-size: 0.8125rem; }
.btn-mark-all:disabled { opacity: 0.5; cursor: not-allowed; }
.filter-row { display: flex; gap: 0.5rem; margin-bottom: 1.25rem; }
.filter-btn { background: #f1f5f9; border: 1px solid #e2e8f0; padding: 0.375rem 0.875rem; border-radius: 0.375rem; cursor: pointer; font-size: 0.8125rem; color: #475569; }
.filter-btn.active { background: #2563eb; color: #fff; border-color: #2563eb; }
.noti-list { display: flex; flex-direction: column; gap: 0.5rem; }
.noti-card { display: flex; justify-content: space-between; align-items: center; background: #fff; border: 1px solid #e2e8f0; border-radius: 0.5rem; padding: 1rem 1.25rem; cursor: pointer; transition: background 0.15s; }
.noti-card:hover { background: #f8fafc; }
.noti-card.unread { background: #eff6ff; border-color: #bfdbfe; }
.noti-content { flex: 1; }
.noti-event { font-size: 0.8125rem; font-weight: 600; color: #1e293b; margin-bottom: 0.25rem; }
.noti-body { font-size: 0.875rem; color: #334155; margin-bottom: 0.25rem; }
.noti-time { }
.unread-dot { width: 10px; height: 10px; border-radius: 50%; background: #2563eb; flex-shrink: 0; margin-left: 0.75rem; }
.text-muted { color: #64748b; }
.text-sm { font-size: 0.8125rem; }
.state-panel { padding: 2rem; text-align: center; border-radius: 0.5rem; }
.state-panel.loading { color: #2563eb; background: #eff6ff; }
.state-panel.error { color: #b91c1c; background: #fef2f2; border: 1px solid #fecaca; }
.state-panel.empty { color: #64748b; background: #f8fafc; border: 1px dashed #cbd5e1; }
</style>
