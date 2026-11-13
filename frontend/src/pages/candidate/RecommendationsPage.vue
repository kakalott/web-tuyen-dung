<template>
  <div class="recommendations-page">
    <h1 class="page-title">Gợi ý việc làm (AI Matching)</h1>

    <!-- CV Selector -->
    <section class="card">
      <div class="cv-selector-row">
        <label class="selector-label">Chọn CV đã xác nhận để phân tích:</label>
        <select v-model="selectedCvId" @change="fetchRecommendations" class="cv-select">
          <option disabled value="">-- Chọn CV --</option>
          <option v-for="cv in confirmedCvs" :key="cv.id" :value="cv.id">
            {{ cv.original_filename }}
          </option>
        </select>
      </div>
      <div v-if="cvLoading" class="text-sm text-muted">Đang tải danh sách CV...</div>
      <div v-else-if="!confirmedCvs.length" class="no-cv-hint">
        <p>Bạn chưa có CV nào được xác nhận trích xuất.</p>
        <router-link to="/candidate/cvs" class="btn-link">Quản lý CV →</router-link>
      </div>
    </section>

    <!-- Results -->
    <div v-if="loading" class="state-panel loading">🤖 AI đang tính toán Match Score...</div>
    <div v-else-if="fetchError" class="state-panel error">
      <p class="error-msg">{{ fetchError }}</p>
      <button v-if="isAiError" @click="fetchRecommendations" class="btn-retry">
        🔄 Thử lại
      </button>
    </div>
    <div v-else-if="selectedCvId && !jobs.length && hasFetched" class="state-panel empty">
      Không tìm thấy công việc phù hợp với CV đã chọn.
    </div>

    <div v-else-if="jobs.length" class="job-results">
      <div v-for="job in jobs" :key="job.id" class="result-card">
        <div class="result-info">
          <h3 class="result-title">{{ job.title }}</h3>
          <p class="result-company">{{ job.company_name }}</p>
          <div class="skill-row">
            <span class="skill-label matched">Kỹ năng khớp:</span>
            <span class="skill-list matched">{{ formatSkills(job.matched_skills) }}</span>
          </div>
          <div class="skill-row">
            <span class="skill-label missing">Kỹ năng thiếu:</span>
            <span class="skill-list missing">{{ formatSkills(job.missing_skills) }}</span>
          </div>
        </div>
        <div class="result-score">
          <div class="score-main">{{ formatScore(job.match_score) }}%</div>
          <div class="score-breakdown">
            <span>Semantic: {{ formatScore(job.semantic_similarity) }}%</span>
            <!-- BR-AI-05: skill_coverage = null → hiển thị "Không áp dụng" -->
            <span>
              Coverage:
              {{ job.skill_coverage !== null && job.skill_coverage !== undefined
                ? formatScore(job.skill_coverage * 100) + '%'
                : 'Không áp dụng'
              }}
            </span>
          </div>
          <router-link :to="`/jobs/${job.id}`" class="btn-detail">Xem chi tiết</router-link>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { cvApi } from '@/services/cvApi';
import { recommendationApi } from '@/services/recommendationApi';

const confirmedCvs = ref([]);
const selectedCvId = ref('');
const jobs = ref([]);
const loading = ref(false);
const cvLoading = ref(true);
const fetchError = ref('');
const isAiError = ref(false);
const hasFetched = ref(false);

onMounted(async () => {
  try {
    const res = await cvApi.getCvs();
    // Lọc chỉ lấy CV đã confirmed (có extraction.review_status = 'confirmed')
    confirmedCvs.value = (res.data || []).filter(
      (cv) => cv.parse_status === 'processed'
    );
  } catch {
    // ignore
  } finally {
    cvLoading.value = false;
  }
});

const fetchRecommendations = async () => {
  if (!selectedCvId.value) return;
  loading.value = true;
  fetchError.value = '';
  isAiError.value = false;
  hasFetched.value = false;
  try {
    const res = await recommendationApi.getRecommendations(selectedCvId.value);
    jobs.value = res.data || [];
  } catch (err) {
    if (err.status === 502 || err.code === 'AI_SERVICE_ERROR') {
      isAiError.value = true;
      fetchError.value = 'Dịch vụ AI đang tạm thời gián đoạn. Vui lòng thử lại sau giây lát.';
    } else {
      fetchError.value = err.message || 'Không thể tải gợi ý việc làm.';
    }
    jobs.value = [];
  } finally {
    loading.value = false;
    hasFetched.value = true;
  }
};

const formatSkills = (skillList) => {
  if (!Array.isArray(skillList) || !skillList.length) return 'Không có';
  return skillList
    .map((s) => (typeof s === 'object' && s !== null ? s.name || s.id : s))
    .filter(Boolean)
    .join(', ') || 'Không có';
};

const formatScore = (v) => {
  if (v === null || v === undefined) return '—';
  return Math.round(v * 10) / 10;
};
</script>

<style scoped>
.recommendations-page { max-width: 900px; }
.page-title { font-size: 1.5rem; font-weight: 700; color: #0f172a; margin-bottom: 1.5rem; }
.card { background: #fff; border: 1px solid #e2e8f0; border-radius: 0.5rem; padding: 1.25rem; margin-bottom: 1.5rem; }
.cv-selector-row { display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; }
.selector-label { font-weight: 500; font-size: 0.875rem; color: #334155; }
.cv-select { padding: 0.5rem 0.75rem; border: 1px solid #cbd5e1; border-radius: 0.375rem; font-size: 0.875rem; min-width: 250px; }
.no-cv-hint { margin-top: 0.75rem; color: #64748b; font-size: 0.875rem; }
.btn-link { color: #2563eb; text-decoration: none; font-weight: 500; }
.job-results { display: flex; flex-direction: column; gap: 0.75rem; }
.result-card { display: flex; justify-content: space-between; align-items: stretch; background: #fff; border: 1px solid #e2e8f0; border-radius: 0.5rem; padding: 1.25rem; }
.result-info { flex: 1; }
.result-title { font-size: 1.0625rem; font-weight: 600; color: #0f172a; margin: 0 0 0.25rem; }
.result-company { font-size: 0.875rem; color: #475569; margin: 0 0 0.75rem; }
.skill-row { font-size: 0.8125rem; margin-bottom: 0.25rem; }
.skill-label { font-weight: 500; }
.skill-label.matched { color: #16a34a; }
.skill-label.missing { color: #dc2626; }
.skill-list.matched { color: #15803d; }
.skill-list.missing { color: #b91c1c; }
.result-score { display: flex; flex-direction: column; align-items: flex-end; justify-content: center; flex-shrink: 0; min-width: 140px; text-align: right; }
.score-main { font-size: 2rem; font-weight: 800; color: #2563eb; line-height: 1.1; }
.score-breakdown { font-size: 0.75rem; color: #64748b; margin-top: 0.375rem; display: flex; flex-direction: column; gap: 0.125rem; }
.btn-detail { display: inline-block; margin-top: 0.75rem; background: #2563eb; color: #fff; padding: 0.375rem 0.75rem; border-radius: 0.375rem; text-decoration: none; font-size: 0.8125rem; font-weight: 500; }
.text-muted { color: #64748b; }
.text-sm { font-size: 0.8125rem; }
.state-panel { padding: 2rem; text-align: center; border-radius: 0.5rem; }
.state-panel.loading { color: #2563eb; background: #eff6ff; }
.state-panel.error { color: #b91c1c; background: #fef2f2; border: 1px solid #fecaca; }
.error-msg { margin-bottom: 0.75rem; font-weight: 500; }
.btn-retry { background: #dc2626; color: #fff; border: none; padding: 0.5rem 1rem; border-radius: 0.375rem; cursor: pointer; font-weight: 500; font-size: 0.875rem; }
.btn-retry:hover { background: #b91c1c; }
.state-panel.empty { color: #64748b; background: #f8fafc; border: 1px dashed #cbd5e1; }
</style>
