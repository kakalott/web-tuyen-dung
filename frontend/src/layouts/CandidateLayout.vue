<template>
  <div class="candidate-layout">
    <aside class="sidebar">
      <div class="sidebar-brand">
        <router-link to="/jobs" class="brand-link">Candidate Portal</router-link>
      </div>
      <nav class="sidebar-nav">
        <router-link to="/candidate/profile" class="nav-item" active-class="active">
          <span>📋</span> Hồ sơ cá nhân
        </router-link>
        <router-link to="/candidate/cvs" class="nav-item" active-class="active">
          <span>📄</span> Quản lý CV
        </router-link>
        <router-link to="/candidate/skills" class="nav-item" active-class="active">
          <span>🎯</span> Kỹ năng
        </router-link>
        <router-link to="/candidate/applications" class="nav-item" active-class="active">
          <span>📨</span> Đơn ứng tuyển
        </router-link>
        <router-link to="/candidate/recommendations" class="nav-item" active-class="active">
          <span>🤖</span> Gợi ý việc làm (AI)
        </router-link>
        <router-link to="/candidate/notifications" class="nav-item" active-class="active">
          <span>🔔</span> Thông báo
        </router-link>
      </nav>
      <div class="sidebar-footer">
        <button @click="handleLogout" class="logout-btn">Đăng xuất</button>
      </div>
    </aside>

    <div class="main-area">
      <header class="top-header">
        <h2 class="greeting">Xin chào, {{ authStore.user?.name }}</h2>
        <router-link to="/candidate/notifications" class="notification-link">
          🔔 Thông báo
        </router-link>
      </header>

      <main class="content-area">
        <router-view />
      </main>
    </div>
  </div>
</template>

<script setup>
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/authStore';

const router = useRouter();
const authStore = useAuthStore();

const handleLogout = async () => {
  await authStore.logout();
  router.push('/login');
};
</script>

<style scoped>
.candidate-layout {
  display: flex;
  min-height: 100vh;
}

.sidebar {
  width: 260px;
  background-color: #1e3a5f;
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
  color: #ffffff;
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
  color: #cbd5e1;
  text-decoration: none;
  font-size: 0.875rem;
  transition: background-color 0.15s, color 0.15s;
}

.nav-item:hover {
  background-color: rgba(255, 255, 255, 0.1);
  color: #ffffff;
}

.nav-item.active {
  background-color: rgba(255, 255, 255, 0.15);
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
  background-color: #f1f5f9;
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

.greeting {
  font-size: 1rem;
  font-weight: 600;
  color: #1e293b;
}

.notification-link {
  color: #475569;
  text-decoration: none;
  font-size: 0.875rem;
}

.notification-link:hover {
  color: #2563eb;
}

.content-area {
  flex: 1;
  padding: 1.5rem;
}
</style>
