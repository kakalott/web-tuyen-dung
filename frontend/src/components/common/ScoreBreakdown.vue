<template>
  <div class="score-breakdown-card">
    <h3 class="card-title">Phân tích Độ phù hợp AI (AI Match Result)</h3>
    <div class="score-hero">
      <div class="score-circle">
        <span class="score-num">{{ formatNum(matchResult?.match_score) }}%</span>
        <span class="score-caption">Match Score</span>
      </div>
      <div class="score-metrics">
        <div class="metric-item">
          <span class="metric-label">Semantic Similarity:</span>
          <span class="metric-value">{{ formatNum(matchResult?.semantic_similarity) }}%</span>
        </div>
        <div class="metric-item">
          <span class="metric-label">Skill Coverage:</span>
          <span class="metric-value">
            {{ matchResult?.skill_coverage !== null && matchResult?.skill_coverage !== undefined
              ? `${formatNum(matchResult.skill_coverage * 100)}%`
              : 'Không áp dụng' }}
          </span>
        </div>
      </div>
    </div>

    <!-- Skills details -->
    <div class="skills-analysis">
      <div class="skill-group matched">
        <h4>Kỹ năng phù hợp (Matched):</h4>
        <div class="pills-list">
          <span v-for="s in matchedSkills" :key="s" class="pill pill-matched">{{ s }}</span>
          <span v-if="!matchedSkills.length" class="empty-pill">Không có</span>
        </div>
      </div>

      <div class="skill-group missing">
        <h4>Kỹ năng còn thiếu (Missing):</h4>
        <div class="pills-list">
          <span v-for="s in missingSkills" :key="s" class="pill pill-missing">{{ s }}</span>
          <span v-if="!missingSkills.length" class="empty-pill">Không có</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  matchResult: {
    type: Object,
    default: () => ({}),
  },
});

const formatNum = (val) => {
  if (val === null || val === undefined) return '0';
  return Math.round(Number(val) * 10) / 10;
};

const matchedSkills = computed(() => {
  return props.matchResult?.matched_skills || [];
});

const missingSkills = computed(() => {
  return props.matchResult?.missing_skills || [];
});
</script>

<style scoped>
.score-breakdown-card {
  background-color: #ffffff;
  border: 1px solid #e2e8f0;
  border-radius: 0.5rem;
  padding: 1.25rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.card-title {
  font-size: 1rem;
  font-weight: 700;
  color: #1e293b;
  margin-bottom: 1rem;
}

.score-hero {
  display: flex;
  align-items: center;
  gap: 1.5rem;
  margin-bottom: 1.25rem;
  padding-bottom: 1rem;
  border-bottom: 1px solid #f1f5f9;
}

.score-circle {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  width: 90px;
  height: 90px;
  border-radius: 50%;
  background: linear-gradient(135deg, #2563eb, #3b82f6);
  color: #ffffff;
}

.score-num {
  font-size: 1.5rem;
  font-weight: 800;
  line-height: 1;
}

.score-caption {
  font-size: 0.65rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-top: 0.25rem;
}

.score-metrics {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.metric-item {
  display: flex;
  justify-content: space-between;
  font-size: 0.875rem;
}

.metric-label {
  color: #64748b;
}

.metric-value {
  font-weight: 700;
  color: #0f172a;
}

.skills-analysis {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.skill-group h4 {
  font-size: 0.8125rem;
  font-weight: 600;
  margin-bottom: 0.35rem;
}

.skill-group.matched h4 {
  color: #166534;
}

.skill-group.missing h4 {
  color: #991b1b;
}

.pills-list {
  display: flex;
  flex-wrap: wrap;
  gap: 0.35rem;
}

.pill {
  padding: 0.2rem 0.5rem;
  border-radius: 0.25rem;
  font-size: 0.75rem;
  font-weight: 500;
}

.pill-matched {
  background-color: #dcfce7;
  color: #166534;
}

.pill-missing {
  background-color: #fee2e2;
  color: #991b1b;
}

.empty-pill {
  font-size: 0.75rem;
  color: #94a3b8;
  font-style: italic;
}
</style>
