<template>
  <div class="application-detail-page">
    <div class="page-header">
      <router-link
        v-if="application.job_id"
        :to="`/company/jobs/${application.job_id}/applications`"
        class="back-link"
      >
        &larr; Quay lại danh sách hồ sơ
      </router-link>
      <h1 class="page-title">Chi tiết Đơn ứng tuyển</h1>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="state-panel loading">Đang tải chi tiết đơn ứng tuyển...</div>

    <!-- Error State -->
    <div v-else-if="error" class="state-panel error">{{ error }}</div>

    <!-- Content Layout -->
    <div v-else class="detail-grid">
      <!-- Cột trái: Thông tin ứng viên, Điểm AI, Lịch phỏng vấn -->
      <div class="left-column">
        <!-- Candidate Card -->
        <div class="card candidate-hero">
          <div class="hero-info">
            <h2 class="candidate-name">{{ application.candidate_name || 'Hồ sơ Ứng viên' }}</h2>
            <p class="job-ref">
              Ứng tuyển vị trí: <strong>{{ application.job?.title || 'Việc làm' }}</strong>
            </p>
            <div class="status-meta">
              <span class="meta-label">Trạng thái hồ sơ:</span>
              <StatusBadge :status="application.status" domain="application" />
              <span class="applied-date">Nộp ngày: {{ formatDate(application.applied_at) }}</span>
            </div>
          </div>

          <!-- Download CV Button Component (Private stream blob) -->
          <CVAccessButtons :application-id="application.id" />
        </div>

        <!-- ScoreBreakdown Component giải thích điểm AI -->
        <ScoreBreakdown v-if="matchResult" :match-result="matchResult" />

        <!-- Component Quản lý Lịch phỏng vấn -->
        <InterviewTimeline
          :interviews="interviews"
          :application-id="application.id"
          @refresh="loadData"
        />
      </div>

      <!-- Cột phải: Form thay đổi trạng thái -->
      <div class="right-column">
        <div class="card sticky-card">
          <h3 class="side-title">Cập nhật Trạng thái Đơn</h3>
          <ApplicationStatusForm
            :current-status="application.status"
            :application-id="application.id"
            @status-updated="handleStatusUpdated"
            @refresh="loadData"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { applicationApi } from '@/services/applicationApi';
import CVAccessButtons from '@/components/company/CVAccessButtons.vue';
import ApplicationStatusForm from '@/components/company/ApplicationStatusForm.vue';
import InterviewTimeline from '@/components/company/InterviewTimeline.vue';
import ScoreBreakdown from '@/components/common/ScoreBreakdown.vue';
import StatusBadge from '@/components/common/StatusBadge.vue';

const route = useRoute();
const applicationId = route.params.id;

const application = ref({});
const interviews = ref([]);
const matchResult = ref(null);
const loading = ref(true);
const error = ref('');

const loadData = async () => {
  loading.value = true;
  error.value = '';
  try {
    const res = await applicationApi.getCompanyApplicationDetail(applicationId);
    application.value = res.data?.application || res.data || {};
    interviews.value = res.data?.interviews || [];
    matchResult.value = res.data?.match_result || null;
  } catch (err) {
    error.value = err.message || 'Không thể tải thông tin đơn ứng tuyển.';
  } finally {
    loading.value = false;
  }
};

const handleStatusUpdated = (newStatus) => {
  application.value.status = newStatus;
  loadData(); // Tải lại để cập nhật toàn bộ logic
};

const formatDate = (ds) => {
  return ds ? new Date(ds).toLocaleString('vi-VN') : '—';
};

onMounted(loadData);
</script>

<style scoped>
.application-detail-page {
  max-width: 1100px;
}

.page-header {
  margin-bottom: 1.5rem;
}

.back-link {
  color: #0284c7;
  text-decoration: none;
  font-size: 0.8125rem;
  font-weight: 500;
  display: inline-block;
  margin-bottom: 0.25rem;
}

.page-title {
  font-size: 1.5rem;
  font-weight: 700;
  color: #0f172a;
}

.detail-grid {
  display: grid;
  grid-template-columns: 2fr 1fr;
  gap: 1.5rem;
}

.left-column {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

.right-column {
  display: flex;
  flex-direction: column;
}

.card {
  background: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 0.5rem;
  padding: 1.25rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.candidate-hero {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
}

.candidate-name {
  font-size: 1.35rem;
  font-weight: 800;
  color: #0f172a;
  margin-bottom: 0.25rem;
}

.job-ref {
  font-size: 0.875rem;
  color: #475569;
  margin-bottom: 0.75rem;
}

.status-meta {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.meta-label {
  font-size: 0.8125rem;
  color: #64748b;
}

.applied-date {
  font-size: 0.75rem;
  color: #94a3b8;
  margin-left: 0.5rem;
}

.sticky-card {
  position: sticky;
  top: 1.5rem;
}

.side-title {
  font-size: 1rem;
  font-weight: 700;
  color: #1e293b;
  margin-bottom: 1rem;
  padding-bottom: 0.5rem;
  border-bottom: 1px solid #f1f5f9;
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
