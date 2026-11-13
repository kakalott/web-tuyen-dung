<template>
  <div class="admin-layout">
    <aside class="sidebar">
      <div class="sidebar-brand">
        <router-link to="/admin/dashboard" class="brand-link">Admin Center</router-link>
      </div>

      <nav class="sidebar-nav">
        <router-link to="/admin/dashboard" class="nav-item" active-class="active">
          <span>📊</span> Dashboard
        </router-link>
        <router-link to="/admin/companies" class="nav-item" active-class="active">
          <span>🏢</span> Duyệt Doanh nghiệp
        </router-link>
        <router-link to="/admin/jobs" class="nav-item" active-class="active">
          <span>📑</span> Kiểm duyệt Tin tuyển dụng
        </router-link>
        <router-link to="/admin/skills" class="nav-item" active-class="active">
          <span>🎯</span> Kỹ năng &amp; Aliases
        </router-link>

        <div class="nav-divider">DANH MỤC TUYỂN DỤNG</div>
        <router-link to="/admin/catalog/job-groups" class="nav-item sub" active-class="active">
          <span>📁</span> Nhóm ngành nghề
        </router-link>
        <router-link to="/admin/catalog/employment-types" class="nav-item sub" active-class="active">
          <span>⏱️</span> Loại hình việc làm
        </router-link>
        <router-link to="/admin/catalog/work-modes" class="nav-item sub" active-class="active">
          <span>📍</span> Hình thức làm việc
        </router-link>
        <router-link to="/admin/catalog/seniority-levels" class="nav-item sub" active-class="active">
          <span>🎖️</span> Cấp bậc kinh nghiệm
        </router-link>
      </nav>

      <div class="sidebar-footer">
        <button @click="handleLogout" class="logout-btn">Đăng xuất</button>
      </div>
    </aside>

    <div class="main-area">
      <header class="top-header">
        <div class="header-left">
          <h2 class="greeting">Quản trị viên: {{ authStore.user?.name }}</h2>
          <span class="role-pill">ADMIN</span>
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
.admin-layout {
  display: flex;
  min-height: 100vh;
}

.sidebar {
  width: 270px;
  background-color: #0b1329;
  color: #e2e8f0;
  display: flex;
  flex-direction: column;
  flex-shrink: 0;
}

.sidebar-brand {
  padding: 1.25rem;
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.brand-link {
  color: #38bdf8;
  text-decoration: none;
  font-size: 1.25rem;
  font-weight: 800;
  letter-spacing: -0.025em;
}

.sidebar-nav {
  flex: 1;
  padding: 1rem;
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
  overflow-y: auto;
}

.nav-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.55rem 0.85rem;
  border-radius: 0.375rem;
  color: #94a3b8;
  text-decoration: none;
  font-size: 0.85rem;
  font-weight: 500;
  transition: all 0.15s;
}

.nav-item:hover {
  background-color: rgba(255, 255, 255, 0.06);
  color: #ffffff;
}

.nav-item.active {
  background-color: #0284c7;
  color: #ffffff;
  font-weight: 700;
}

.nav-item.sub {
  font-size: 0.8rem;
  padding-left: 1.25rem;
}

.nav-divider {
  font-size: 0.65rem;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: #64748b;
  margin: 0.85rem 0 0.35rem 0.5rem;
  font-weight: 700;
}

.sidebar-footer {
  padding: 1rem;
  border-top: 1px solid rgba(255, 255, 255, 0.08);
}

.logout-btn {
  width: 100%;
  padding: 0.5rem;
  background: transparent;
  border: 1px solid rgba(255, 255, 255, 0.15);
  color: #cbd5e1;
  border-radius: 0.375rem;
  cursor: pointer;
  font-size: 0.85rem;
}

.logout-btn:hover {
  background-color: rgba(255, 255, 255, 0.08);
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
  font-weight: 700;
  color: #0f172a;
}

.role-pill {
  font-size: 0.7rem;
  padding: 0.15rem 0.45rem;
  border-radius: 9999px;
  background-color: #fef3c7;
  color: #92400e;
  font-weight: 800;
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
