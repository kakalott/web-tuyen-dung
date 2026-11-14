<template>
  <div class="company-layout">
    <aside class="sidebar">
      <div class="sidebar-brand">
        <router-link to="/company/jobs" class="brand-link">Company Portal</router-link>
      </div>
      <nav class="sidebar-nav">
        <router-link to="/company/profile" class="nav-item" active-class="active">
          <span>🏢</span> Hồ sơ Công ty
        </router-link>
        <router-link to="/company/jobs" class="nav-item" active-class="active">
          <span>💼</span> Quản lý Tin tuyển dụng
        </router-link>
      </nav>
      <div class="sidebar-footer">
        <button @click="handleLogout" class="logout-btn">Đăng xuất</button>
      </div>
    </aside>

    <div class="main-area">
      <header class="top-header">
        <div class="header-left">
          <h2 class="greeting">Xin chào, {{ authStore.user?.name }}</h2>
          <span v-if="verificationStatus" class="verification-pill" :class="verificationStatus">
            {{ verificationStatus === 'verified' ? 'Đã xác minh' : 'Chưa xác minh' }}
          </span>
        </div>
        <div class="header-right">
          <router-link to="/candidate/notifications" class="notification-link">
            🔔 Thông báo
          </router-link>
        </div>
      </header>

      <main class="content-area">
        <router-view />
      </main>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/authStore';
import { companyApi } from '@/services/companyApi';

const router = useRouter();
const authStore = useAuthStore();
const localCompany = ref(null);

const verificationStatus = computed(() => {
  return (
    localCompany.value?.verification_status ||
    authStore.user?.company?.verification_status ||
    'unverified'
  );
});

onMounted(async () => {
  try {
    const res = await companyApi.getProfile();
    const comp = res.data?.company || res.data;
    if (comp) {
      localCompany.value = comp;
      if (authStore.user) {
        authStore.user.company = { ...authStore.user.company, ...comp };
      }
    }
  } catch {
    // ignore
  }
});

const handleLogout = async () => {
  await authStore.logout();
  router.push('/login');
};
</script>

<style scoped>
.company-layout {
  display: flex;
  min-height: 100vh;
}

.sidebar {
  width: 260px;
  background-color: #0f172a;
  color: #e2e8f0;
  display: flex;
  flex-direction: column;
  flex-shrink: 0;
}

.sidebar-brand {
  padding: 1.25rem;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.brand-link {
  color: #38bdf8;
  text-decoration: none;
  font-size: 1.25rem;
  font-weight: 700;
}

.sidebar-nav {
  flex: 1;
  padding: 1rem;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.nav-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.625rem 0.875rem;
  border-radius: 0.375rem;
  color: #94a3b8;
  text-decoration: none;
  font-size: 0.875rem;
  transition: background-color 0.15s, color 0.15s;
}

.nav-item:hover {
  background-color: rgba(255, 255, 255, 0.08);
  color: #ffffff;
}

.nav-item.active {
  background-color: #0284c7;
  color: #ffffff;
  font-weight: 600;
}

.sidebar-footer {
  padding: 1rem;
  border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.logout-btn {
  width: 100%;
  padding: 0.5rem;
  background: transparent;
  border: 1px solid rgba(255, 255, 255, 0.2);
  color: #cbd5e1;
  border-radius: 0.375rem;
  cursor: pointer;
  font-size: 0.875rem;
}

.logout-btn:hover {
  background-color: rgba(255, 255, 255, 0.1);
  color: #ffffff;
}

.main-area {
  flex: 1;
  display: flex;
  flex-direction: column;
  overflow-y: auto;
  background-color: #f8fafc;
}

.top-header {
  background-color: #ffffff;
  padding: 1rem 1.5rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-bottom: 1px solid #e2e8f0;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}

.header-left {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.greeting {
  font-size: 1rem;
  font-weight: 600;
  color: #1e293b;
}

.verification-pill {
  font-size: 0.75rem;
  padding: 0.2rem 0.5rem;
  border-radius: 9999px;
  font-weight: 600;
}

.verification-pill.verified {
  background-color: #dcfce7;
  color: #166534;
}

.verification-pill.unverified {
  background-color: #fef3c7;
  color: #92400e;
}

.notification-link {
  color: #475569;
  text-decoration: none;
  font-size: 0.875rem;
}

.notification-link:hover {
  color: #0284c7;
}

.content-area {
  flex: 1;
  padding: 1.5rem;
}
</style>
