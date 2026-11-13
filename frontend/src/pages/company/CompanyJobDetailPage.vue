<template>
  <div class="job-detail-page">
    <div v-if="loading" class="state-panel loading">Đang tải chi tiết tin tuyển dụng...</div>
    <div v-else-if="error" class="state-panel error">{{ error }}</div>

    <div v-else-if="job" class="job-container">
      <!-- Header bar -->
      <div class="detail-header">
        <div class="header-titles">
          <router-link to="/company/jobs" class="back-link">← Danh sách tin</router-link>
          <h1 class="job-title">{{ job.title }}</h1>
          <div class="badges-row">
            <StatusBadge :status="job.review_status" domain="review" />
            <StatusBadge :status="job.publication_status" domain="publication" />
            <span v-if="job.expires_at" class="expiry-text">
              Hạn nộp: {{ formatDate(job.expires_at) }}
            </span>
          </div>
        </div>

        <div class="header-actions">
          <router-link :to="`/company/jobs/${job.id}/edit`" class="btn-edit">
            ✏️ Sửa tin
          </router-link>
          <router-link :to="`/company/jobs/${job.id}/applications`" class="btn-view-apps">
            👥 Xem danh sách ứng viên
          </router-link>
        </div>
      </div>

      <!-- Hiển thị lý do từ chối (BR-JOB-03) -->
      <div v-if="job.review_status === 'rejected'" class="alert-box rejected">
        <h4 class="alert-title">❌ Tin tuyển dụng bị từ chối phê duyệt</h4>
        <p class="alert-desc">
          <strong>Lý do từ chối:</strong> {{ job.rejection_reason || 'Không có lý do chi tiết.' }}
        </p>
        <p class="alert-hint">Vui lòng chỉnh sửa nội dung tin theo phản hồi của Admin và lưu lại để được duyệt lại.</p>
      </div>

      <!-- Hiển thị lý do bị ẩn (BR-JOB-07) -->
      <div v-if="job.publication_status === 'hidden'" class="alert-box hidden">
        <h4 class="alert-title">🔒 Tin tuyển dụng đang bị ẩn bởi Admin</h4>
        <p class="alert-desc">
          <strong>Lý do ẩn:</strong> {{ job.hidden_reason || 'Không có lý do chi tiết.' }}
        </p>
      </div>

      <!-- Nội dung chi tiết -->
      <div class="detail-grid">
        <div class="main-column">
          <section class="card">
            <h3 class="section-title">Mô tả công việc</h3>
            <p class="pre-content">{{ job.description }}</p>
          </section>

          <section class="card">
            <h3 class="section-title">Yêu cầu công việc</h3>
            <p class="pre-content">{{ job.requirements_text }}</p>
          </section>
        </div>

        <div class="sidebar-column">
          <section class="card">
            <h3 class="section-title">Thông tin bổ sung</h3>
            <ul class="meta-list">
              <li>
                <span class="meta-label">Nhóm ngành:</span>
                <span class="meta-val">{{ job.job_group?.name || 'Chưa phân loại' }}</span>
              </li>
              <li>
                <span class="meta-label">Hình thức:</span>
                <span class="meta-val">{{ job.employment_type?.name || '—' }}</span>
              </li>
              <li>
                <span class="meta-label">Chế độ:</span>
                <span class="meta-val">{{ job.work_mode?.name || '—' }}</span>
              </li>
              <li>
                <span class="meta-label">Cấp bậc:</span>
                <span class="meta-val">{{ job.seniority_level?.name || '—' }}</span>
              </li>
            </ul>
          </section>

          <section class="card">
            <h3 class="section-title">Kỹ năng yêu cầu</h3>
            <div class="skills-wrap">
              <div class="skill-sub">
                <span class="sub-label required">Bắt buộc:</span>
                <div class="pills">
                  <span v-for="s in requiredSkills" :key="s.id" class="skill-pill required">
                    {{ s.name }}
                  </span>
                  <span v-if="!requiredSkills.length" class="empty-text">Không có</span>
                </div>
              </div>

              <div class="skill-sub">
                <span class="sub-label preferred">Ưu tiên:</span>
                <div class="pills">
                  <span v-for="s in preferredSkills" :key="s.id" class="skill-pill preferred">
                    {{ s.name }}
                  </span>
                  <span v-if="!preferredSkills.length" class="empty-text">Không có</span>
                </div>
              </div>
            </div>
          </section>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { jobApi } from '@/services/jobApi';
import StatusBadge from '@/components/common/StatusBadge.vue';

const route = useRoute();
const job = ref(null);
const loading = ref(true);
const error = ref('');

const requiredSkills = computed(() => {
  return (job.value?.skills || []).filter(
    (s) => s.pivot?.requirement_type === 'required'
  );
});

const preferredSkills = computed(() => {
  return (job.value?.skills || []).filter(
    (s) => s.pivot?.requirement_type === 'preferred'
  );
});

const formatDate = (ds) => {
  return ds ? new Date(ds).toLocaleDateString('vi-VN') : '—';
};

onMounted(async () => {
  try {
    const res = await jobApi.showOwn(route.params.jobId);
    job.value = res.data?.job || res.data;
  } catch (err) {
    error.value = err.message || 'Không thể tải chi tiết tin tuyển dụng.';
  } finally {
    loading.value = false;
  }
});
</script>

<style scoped>
.job-detail-page {
  max-width: 1050px;
}

.detail-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1.5rem;
  background-color: #ffffff;
  padding: 1.5rem;
  border-radius: 0.5rem;
  border: 1px solid #e2e8f0;
}

.back-link {
  color: #0284c7;
  text-decoration: none;
  font-size: 0.8125rem;
  font-weight: 500;
  display: inline-block;
  margin-bottom: 0.5rem;
}

.job-title {
  font-size: 1.5rem;
  font-weight: 800;
  color: #0f172a;
  margin-bottom: 0.5rem;
}

.badges-row {
  display: flex;
  align-items: center;
  gap: 0.75rem;
}

.expiry-text {
  font-size: 0.8125rem;
  color: #64748b;
}

.header-actions {
  display: flex;
  gap: 0.75rem;
}

.btn-edit {
  background-color: #f1f5f9;
  color: #334155;
  padding: 0.6rem 1rem;
  border-radius: 0.375rem;
  text-decoration: none;
  font-size: 0.875rem;
  font-weight: 600;
  border: 1px solid #cbd5e1;
}

.btn-view-apps {
  background-color: #0284c7;
  color: #ffffff;
  padding: 0.6rem 1.25rem;
  border-radius: 0.375rem;
  text-decoration: none;
  font-size: 0.875rem;
  font-weight: 600;
}

.alert-box {
  padding: 1rem 1.25rem;
  border-radius: 0.5rem;
  border-left: 4px solid;
  margin-bottom: 1.5rem;
}

.alert-box.rejected {
  background-color: #fef2f2;
  border-color: #dc2626;
  color: #991b1b;
}

.alert-box.hidden {
  background-color: #fff7ed;
  border-color: #ea580c;
  color: #9a3412;
}

.alert-title {
  font-size: 0.95rem;
  font-weight: 700;
  margin-bottom: 0.25rem;
}

.alert-desc {
  font-size: 0.875rem;
  line-height: 1.4;
}

.alert-hint {
  font-size: 0.8125rem;
  margin-top: 0.5rem;
  font-style: italic;
  opacity: 0.85;
}

.detail-grid {
  display: grid;
  grid-template-columns: 2fr 1fr;
  gap: 1.5rem;
}

.card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 0.5rem;
  padding: 1.25rem;
  margin-bottom: 1.5rem;
}

.section-title {
  font-size: 1rem;
  font-weight: 700;
  color: #1e293b;
  margin-bottom: 0.75rem;
  padding-bottom: 0.35rem;
  border-bottom: 1px solid #f1f5f9;
}

.pre-content {
  font-size: 0.875rem;
  color: #334155;
  white-space: pre-wrap;
  line-height: 1.6;
}

.meta-list {
  list-style: none;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.meta-list li {
  display: flex;
  justify-content: space-between;
  font-size: 0.8125rem;
}

.meta-label {
  color: #64748b;
}

.meta-val {
  font-weight: 600;
  color: #0f172a;
}

.skills-wrap {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.sub-label {
  font-size: 0.75rem;
  font-weight: 700;
  display: block;
  margin-bottom: 0.25rem;
}

.sub-label.required {
  color: #dc2626;
}

.sub-label.preferred {
  color: #0284c7;
}

.pills {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
}

.skill-pill {
  padding: 0.2rem 0.5rem;
  border-radius: 0.25rem;
  font-size: 0.75rem;
  font-weight: 600;
}

.skill-pill.required {
  background-color: #fee2e2;
  color: #991b1b;
}

.skill-pill.preferred {
  background-color: #e0f2fe;
  color: #0369a1;
}

.empty-text {
  font-size: 0.75rem;
  color: #94a3b8;
  font-style: italic;
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
