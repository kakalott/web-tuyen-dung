import { defineStore } from 'pinia';
import { authApi } from '@/services/authApi';

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null,
    role: null,
    isAuthenticated: false,
    loading: false,
    initialized: false,
  }),
  actions: {
    async loadMe() {
      this.loading = true;
      try {
        const response = await authApi.me();
        this.user = response.data;
        this.role = response.data?.role ?? null;
        this.isAuthenticated = Boolean(this.user);
        return this.user;
      } catch (err) {
        this.clear();
        return null;
      } finally {
        this.loading = false;
        this.initialized = true;
      }
    },
    async logout() {
      try {
        await authApi.logout();
      } catch (err) {
        // Tiếp tục xóa state cục bộ kể cả khi request logout server fail
      } finally {
        this.clear();
      }
    },
    clear() {
      this.user = null;
      this.role = null;
      this.isAuthenticated = false;
      this.initialized = true;
    },
  },
});
