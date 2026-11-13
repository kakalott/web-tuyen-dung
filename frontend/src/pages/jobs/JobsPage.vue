<template>
  <div class="jobs-search-page">
    <!-- Hero / Filter Section -->
    <div class="search-hero">
      <h1 class="hero-title">Tìm kiếm Việc làm AI Thông minh</h1>
      <p class="hero-subtitle">Khám phá cơ hội nghề nghiệp phù hợp với kỹ năng và định hướng của bạn</p>

      <form @submit.prevent="handleSearch" class="search-bar-form">
        <div class="search-input-group">
          <span class="icon">🔍</span>
          <input
            v-model="filters.keyword"
            type="text"
            placeholder="Tìm theo chức danh, từ khóa, công nghệ..."
            class="main-search-input"
          />
        </div>

        <button type="submit" class="btn-search-hero">Tìm kiếm</button>
      </form>

      <!-- Advanced Filters row -->
      <div class="filters-row">
        <select v-model="filters.job_group_id" @change="handleSearch" class="filter-select">
          <option value="">Tất cả Nhóm ngành</option>
          <option v-for="jg in lookups.job_groups" :key="jg.id" :value="jg.id">{{ jg.name }}</option>
        </select>

        <select v-model="filters.employment_type_id" @change="handleSearch" class="filter-select">
          <option value="">Tất cả Hình thức</option>
          <option v-for="et in lookups.employment_types" :key="et.id" :value="et.id">{{ et.name }}</option>
        </select>

        <select v-model="filters.work_mode_id" @change="handleSearch" class="filter-select">
          <option value="">Tất cả Chế độ làm việc</option>
          <option v-for="wm in lookups.work_modes" :key="wm.id" :value="wm.id">{{ wm.name }}</option>
        </select>

        <select v-model="filters.seniority_level_id" @change="handleSearch" class="filter-select">
          <option value="">Tất cả Cấp bậc</option>
          <option v-for="sl in lookups.seniority_levels" :key="sl.id" :value="sl.id">{{ sl.name }}</option>
        </select>
      </div>
    </div>

    <!-- Results Section -->
    <div class="results-container">
      <div v-if="loading" class="state-panel loading">Đang tìm kiếm việc làm...</div>
      <div v-else-if="error" class="state-panel error">{{ error }}</div>

      <div v-else-if="jobs.length" class="jobs-list">
        <div class="results-meta">Tìm thấy <strong>{{ totalJobs }}</strong> việc làm phù hợp</div>

        <div v-for="job in jobs" :key="job.id" class="job-card">
          <div class="card-main">
            <h3 class="job-title">
              <router-link :to="`/jobs/${job.id}`" class="job-title-link">
                {{ job.title }}
              </router-link>
            </h3>

            <p class="company-name">🏢 {{ job.company?.company_name || 'Doanh nghiệp ẩn danh' }}</p>

            <div class="tags-row">
              <span v-if="job.job_group?.name" class="tag">{{ job.job_group.name }}</span>
              <span v-if="job.employment_type?.name" class="tag">{{ job.employment_type.name }}</span>
              <span v-if="job.work_mode?.name" class="tag highlight">{{ job.work_mode.name }}</span>
              <span v-if="job.seniority_level?.name" class="tag">{{ job.seniority_level.name }}</span>
            </div>

            <!-- Skills preview -->
            <div v-if="job.skills?.length" class="skills-preview">
              <span v-for="s in job.skills.slice(0, 5)" :key="s.id" class="skill-tag">
                {{ s.name }}
              </span>
              <span v-if="job.skills.length > 5" class="more-skills">+{{ job.skills.length - 5 }}</span>
            </div>
          </div>

          <div class="card-action">
            <span class="expiry-date">Hạn nộp: {{ formatDate(job.expires_at) }}</span>
            <router-link :to="`/jobs/${job.id}`" class="btn-apply-view">Xem chi tiết &amp; Ứng tuyển</router-link>
          </div>
        </div>
      </div>

      <EmptyState v-else text="Không tìm thấy công việc nào phù hợp với điều kiện tìm kiếm." />
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { apiClient } from '@/services/apiClient';
import EmptyState from '@/components/common/EmptyState.vue';

const jobs = ref([]);
const totalJobs = ref(0);
const loading = ref(true);
const error = ref('');

const filters = ref({
  keyword: '',
  job_group_id: '',
  employment_type_id: '',
  work_mode_id: '',
  seniority_level_id: '',
});

const lookups = ref({
  job_groups: [],
  employment_types: [],
  work_modes: [],
  seniority_levels: [],
});

const loadLookups = async () => {
  try {
    const res = await apiClient.request('GET', '/catalog/lookups');
    lookups.value = res.data || {};
  } catch {
    // fallback
  }
};

const handleSearch = async () => {
  loading.value = true;
  error.value = '';
  try {
    const params = new URLSearchParams();
    if (filters.value.keyword) params.append('keyword', filters.value.keyword);
    if (filters.value.job_group_id) params.append('job_group_id', filters.value.job_group_id);
    if (filters.value.employment_type_id) params.append('employment_type_id', filters.value.employment_type_id);
    if (filters.value.work_mode_id) params.append('work_mode_id', filters.value.work_mode_id);
    if (filters.value.seniority_level_id) params.append('seniority_level_id', filters.value.seniority_level_id);

    const q = params.toString();
    const res = await apiClient.request('GET', `/jobs${q ? `?${q}` : ''}`);
    jobs.value = res.data?.items || res.data || [];
    totalJobs.value = res.total ?? jobs.value.length;
  } catch (err) {
    error.value = err.message || 'Không thể tải danh sách việc làm.';
  } finally {
    loading.value = false;
  }
};

const formatDate = (ds) => {
  return ds ? new Date(ds).toLocaleDateString('vi-VN') : 'Không giới hạn';
};

onMounted(async () => {
  await loadLookups();
  await handleSearch();
});
</script>

<style scoped>
.jobs-search-page {
  max-width: 1100px;
  margin: 0 auto;
}

.search-hero {
  background: linear-gradient(135deg, #1e3a8a, #0284c7);
  color: #ffffff;
  padding: 2.5rem 1.5rem;
  border-radius: 0.75rem;
  margin-bottom: 2rem;
  text-align: center;
}

.hero-title {
  font-size: 2rem;
  font-weight: 800;
  margin-bottom: 0.5rem;
}

.hero-subtitle {
  font-size: 1rem;
  opacity: 0.9;
  margin-bottom: 1.5rem;
}

.search-bar-form {
  display: flex;
  max-width: 750px;
  margin: 0 auto 1.25rem;
  background-color: #ffffff;
  border-radius: 0.5rem;
  padding: 0.35rem;
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.2);
}

.search-input-group {
  display: flex;
  align-items: center;
  flex: 1;
  padding: 0 0.75rem;
}

.icon {
  font-size: 1.1rem;
  margin-right: 0.5rem;
}

.main-search-input {
  width: 100%;
  border: none;
  font-size: 0.95rem;
  color: #0f172a;
  outline: none;
}

.btn-search-hero {
  background-color: #f59e0b;
  color: #0f172a;
  border: none;
  padding: 0.75rem 1.75rem;
  border-radius: 0.375rem;
  font-weight: 700;
  font-size: 0.95rem;
  cursor: pointer;
  transition: background-color 0.15s;
}

.btn-search-hero:hover {
  background-color: #d97706;
}

.filters-row {
  display: flex;
  justify-content: center;
  flex-wrap: wrap;
  gap: 0.75rem;
  max-width: 850px;
  margin: 0 auto;
}

.filter-select {
  padding: 0.45rem 0.85rem;
  border-radius: 0.375rem;
  border: 1px solid rgba(255, 255, 255, 0.3);
  background-color: rgba(255, 255, 255, 0.15);
  color: #ffffff;
  font-size: 0.8125rem;
  outline: none;
}

.filter-select option {
  color: #0f172a;
  background-color: #ffffff;
}

.results-meta {
  font-size: 0.875rem;
  color: #64748b;
  margin-bottom: 1rem;
}

.jobs-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.job-card {
  background-color: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 0.5rem;
  padding: 1.5rem;
  display: flex;
  justify-content: space-between;
  align-items: center;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
  transition: transform 0.15s, box-shadow 0.15s;
}

.job-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
  border-color: #bae6fd;
}

.card-main {
  flex: 1;
}

.job-title {
  font-size: 1.25rem;
  font-weight: 700;
  margin-bottom: 0.25rem;
}

.job-title-link {
  color: #0f172a;
  text-decoration: none;
}

.job-title-link:hover {
  color: #0284c7;
}

.company-name {
  font-size: 0.875rem;
  color: #475569;
  font-weight: 500;
  margin-bottom: 0.75rem;
}

.tags-row {
  display: flex;
  flex-wrap: wrap;
  gap: 0.4rem;
  margin-bottom: 0.75rem;
}

.tag {
  background-color: #f1f5f9;
  color: #475569;
  padding: 0.2rem 0.5rem;
  border-radius: 0.25rem;
  font-size: 0.75rem;
  font-weight: 500;
}

.tag.highlight {
  background-color: #e0f2fe;
  color: #0369a1;
  font-weight: 600;
}

.skills-preview {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
}

.skill-tag {
  background-color: #f8fafc;
  border: 1px solid #e2e8f0;
  color: #334155;
  padding: 0.15rem 0.45rem;
  border-radius: 0.25rem;
  font-size: 0.75rem;
}

.more-skills {
  font-size: 0.75rem;
  color: #94a3b8;
  align-self: center;
}

.card-action {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 0.5rem;
  flex-shrink: 0;
  margin-left: 1.5rem;
}

.expiry-date {
  font-size: 0.75rem;
  color: #94a3b8;
}

.btn-apply-view {
  background-color: #0284c7;
  color: #ffffff;
  padding: 0.6rem 1.25rem;
  border-radius: 0.375rem;
  text-decoration: none;
  font-weight: 600;
  font-size: 0.875rem;
  transition: background-color 0.15s;
}

.btn-apply-view:hover {
  background-color: #0369a1;
}

.state-panel {
  padding: 3rem;
  text-align: center;
  border-radius: 0.5rem;
}

.state-panel.loading {
  color: #0284c7;
  background: #f0f9ff;
}

.state-panel.error {
  color: #b91c1c;
  background: #fef2f2;
}
</style>
