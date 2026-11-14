<template>
  <div class="profile-page">
    <h1 class="page-title">Hồ sơ cá nhân</h1>

    <!-- Loading State -->
    <div v-if="loading" class="state-panel loading">Đang tải dữ liệu...</div>

    <!-- Error State -->
    <div v-else-if="error" class="state-panel error" role="alert">
      {{ error }}
      <button @click="loadProfile" class="retry-btn">Thử lại</button>
    </div>

    <!-- Success State -->
    <div v-else class="sections-grid">
      <!-- Thông tin cơ bản -->
      <section class="card">
        <h3 class="card-title">Thông tin cơ bản</h3>
        <div v-if="successMsg" class="success-msg">{{ successMsg }}</div>
        <form @submit.prevent="saveProfile" class="form">
          <div class="form-group">
            <label>Họ và tên</label>
            <input v-model="profile.full_name" type="text" placeholder="Nguyễn Văn A" />
          </div>
          <div class="form-group">
            <label>Số điện thoại</label>
            <input v-model="profile.phone" type="tel" placeholder="0901234567" />
          </div>
          <div class="form-group">
            <label>Địa chỉ</label>
            <input v-model="profile.address" type="text" placeholder="TP. Hồ Chí Minh" />
          </div>
          <div class="form-group">
            <label>Ngày sinh</label>
            <input v-model="profile.date_of_birth" type="date" />
          </div>
          <div class="form-group">
            <label>Giới thiệu bản thân</label>
            <textarea v-model="profile.summary" rows="4" placeholder="Mô tả ngắn gọn về bản thân..."></textarea>
          </div>
          <button type="submit" class="btn-primary" :disabled="saving">
            {{ saving ? 'Đang lưu...' : 'Lưu thông tin' }}
          </button>
        </form>
      </section>

      <!-- Học vấn -->
      <section class="card">
        <div class="card-header">
          <h3 class="card-title">Học vấn</h3>
          <button @click="showAddEduForm = !showAddEduForm" class="btn-add">+ Thêm</button>
        </div>

        <!-- Form thêm học vấn -->
        <form v-if="showAddEduForm" @submit.prevent="addEducation" class="sub-form">
          <div class="form-group">
            <label>Trường học</label>
            <input v-model="newEdu.school_name" required />
          </div>
          <div class="form-group">
            <label>Bằng cấp</label>
            <input v-model="newEdu.degree" required />
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Bắt đầu</label>
              <input v-model="newEdu.start_date" type="date" required />
            </div>
            <div class="form-group">
              <label>Kết thúc</label>
              <input v-model="newEdu.end_date" type="date" />
            </div>
          </div>
          <div class="form-actions">
            <button type="submit" class="btn-primary btn-sm">Lưu</button>
            <button type="button" @click="showAddEduForm = false" class="btn-secondary btn-sm">Hủy</button>
          </div>
        </form>

        <!-- Empty State -->
        <p v-if="!educations.length" class="empty-hint">Chưa có thông tin học vấn.</p>

        <ul class="item-list">
          <li v-for="edu in educations" :key="edu.id" class="item-row">
            <div>
              <strong>{{ edu.school_name }}</strong>
              <span class="text-muted"> — {{ edu.degree }}</span>
              <div class="text-sm text-muted">{{ edu.start_date }} → {{ edu.end_date || 'Hiện tại' }}</div>
            </div>
            <button @click="promptDelete('education', edu.id)" class="btn-danger-sm">Xóa</button>
          </li>
        </ul>
      </section>

      <!-- Kinh nghiệm -->
      <section class="card">
        <div class="card-header">
          <h3 class="card-title">Kinh nghiệm làm việc</h3>
          <button @click="showAddExpForm = !showAddExpForm" class="btn-add">+ Thêm</button>
        </div>

        <form v-if="showAddExpForm" @submit.prevent="addExperience" class="sub-form">
          <div class="form-group">
            <label>Công ty</label>
            <input v-model="newExp.company_name" required />
          </div>
          <div class="form-group">
            <label>Vị trí</label>
            <input v-model="newExp.position" required />
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Bắt đầu</label>
              <input v-model="newExp.start_date" type="date" required />
            </div>
            <div class="form-group">
              <label>Kết thúc</label>
              <input v-model="newExp.end_date" type="date" />
            </div>
          </div>
          <div class="form-group">
            <label>Mô tả công việc</label>
            <textarea v-model="newExp.description" rows="2"></textarea>
          </div>
          <div class="form-actions">
            <button type="submit" class="btn-primary btn-sm">Lưu</button>
            <button type="button" @click="showAddExpForm = false" class="btn-secondary btn-sm">Hủy</button>
          </div>
        </form>

        <p v-if="!experiences.length" class="empty-hint">Chưa có kinh nghiệm làm việc.</p>

        <ul class="item-list">
          <li v-for="exp in experiences" :key="exp.id" class="item-row">
            <div>
              <strong>{{ exp.position }}</strong>
              <span class="text-muted"> tại {{ exp.company_name }}</span>
              <div class="text-sm text-muted">{{ exp.start_date }} → {{ exp.end_date || 'Hiện tại' }}</div>
            </div>
            <button @click="promptDelete('experience', exp.id)" class="btn-danger-sm">Xóa</button>
          </li>
        </ul>
      </section>

      <!-- Kỹ năng tổng hợp từ các CV đã xác nhận -->
      <section class="card skills-summary-card">
        <div class="card-header">
          <div>
            <h3 class="card-title">Kỹ năng tổng hợp từ các CV đã xác nhận</h3>
            <p class="section-desc">Kỹ năng được AI tự động lọc và gắn trực tiếp theo từng CV của bạn</p>
          </div>
          <router-link to="/candidate/cvs" class="btn-manage-cv-skills">
            📄 Quản lý trong CV →
          </router-link>
        </div>

        <div v-if="skillsLoading" class="text-sm text-muted">Đang tải danh sách kỹ năng...</div>
        <div v-else-if="skills.length" class="skills-wrap">
          <span v-for="skill in skills" :key="skill.id" class="skill-badge">
            {{ skill.name }}
          </span>
        </div>
        <div v-else class="empty-skills-box">
          <p class="empty-hint">Chưa có kỹ năng nào được xác nhận từ CV.</p>
          <router-link to="/candidate/cvs" class="btn-upload-cv-cta">
            Tải lên &amp; Xác nhận CV ngay
          </router-link>
        </div>
      </section>
    </div>

    <!-- ConfirmModal -->
    <div v-if="showDeleteModal" class="modal-overlay" @click.self="showDeleteModal = false">
      <div class="modal-card">
        <p>Bạn có chắc chắn muốn xóa bản ghi này?</p>
        <div class="modal-actions">
          <button @click="confirmDelete" class="btn-danger">Xóa</button>
          <button @click="showDeleteModal = false" class="btn-secondary">Hủy</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { candidateApi } from '@/services/candidateApi';
import { skillApi } from '@/services/skillApi';

const profile = ref({});
const educations = ref([]);
const experiences = ref([]);
const skills = ref([]);
const skillsLoading = ref(true);
const loading = ref(true);
const saving = ref(false);
const error = ref('');
const successMsg = ref('');

const showAddEduForm = ref(false);
const showAddExpForm = ref(false);
const newEdu = ref({ school_name: '', degree: '', start_date: '', end_date: '' });
const newExp = ref({ company_name: '', position: '', start_date: '', end_date: '', description: '' });

const showDeleteModal = ref(false);
let deleteTarget = { type: '', id: null };

const loadSkills = async () => {
  skillsLoading.value = true;
  try {
    const res = await skillApi.getMySkills();
    skills.value = res.data?.skills || res.data || [];
  } catch {
    skills.value = [];
  } finally {
    skillsLoading.value = false;
  }
};

const loadProfile = async () => {
  loading.value = true;
  error.value = '';
  try {
    const res = await candidateApi.getProfile();
    profile.value = res.data?.profile || res.data || {};
    educations.value = res.data?.educations || [];
    experiences.value = res.data?.experiences || [];
  } catch (err) {
    error.value = err.message || 'Không thể tải hồ sơ.';
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  loadProfile();
  loadSkills();
});

const saveProfile = async () => {
  saving.value = true;
  successMsg.value = '';
  try {
    await candidateApi.updateProfile(profile.value);
    successMsg.value = 'Đã lưu thành công.';
    setTimeout(() => (successMsg.value = ''), 3000);
  } catch (err) {
    alert(err.message || 'Lưu thất bại.');
  } finally {
    saving.value = false;
  }
};

const addEducation = async () => {
  try {
    const res = await candidateApi.addEducation(newEdu.value);
    educations.value.push(res.data);
    newEdu.value = { school_name: '', degree: '', start_date: '', end_date: '' };
    showAddEduForm.value = false;
  } catch (err) {
    alert(err.message || 'Thêm học vấn thất bại.');
  }
};

const addExperience = async () => {
  try {
    const res = await candidateApi.addExperience(newExp.value);
    experiences.value.push(res.data);
    newExp.value = { company_name: '', position: '', start_date: '', end_date: '', description: '' };
    showAddExpForm.value = false;
  } catch (err) {
    alert(err.message || 'Thêm kinh nghiệm thất bại.');
  }
};

const promptDelete = (type, id) => {
  deleteTarget = { type, id };
  showDeleteModal.value = true;
};

const confirmDelete = async () => {
  try {
    if (deleteTarget.type === 'education') {
      await candidateApi.deleteEducation(deleteTarget.id);
      educations.value = educations.value.filter((e) => e.id !== deleteTarget.id);
    } else {
      await candidateApi.deleteExperience(deleteTarget.id);
      experiences.value = experiences.value.filter((e) => e.id !== deleteTarget.id);
    }
  } catch (err) {
    alert(err.message || 'Xóa thất bại.');
  } finally {
    showDeleteModal.value = false;
  }
};
</script>

<style scoped>
.profile-page { max-width: 960px; }
.page-title { font-size: 1.5rem; font-weight: 700; color: #0f172a; margin-bottom: 1.5rem; }
.sections-grid { display: flex; flex-direction: column; gap: 1.5rem; }
.card { background: #fff; border: 1px solid #e2e8f0; border-radius: 0.5rem; padding: 1.5rem; }
.card-title { font-size: 1.125rem; font-weight: 600; color: #1e293b; margin-bottom: 1rem; }
.card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
.card-header .card-title { margin-bottom: 0; }
.form { display: flex; flex-direction: column; gap: 1rem; }
.form-group { display: flex; flex-direction: column; gap: 0.25rem; }
.form-group label { font-size: 0.8125rem; font-weight: 500; color: #475569; }
.form-group input, .form-group textarea, .form-group select { padding: 0.5rem 0.75rem; border: 1px solid #cbd5e1; border-radius: 0.375rem; font-size: 0.875rem; outline: none; }
.form-group input:focus, .form-group textarea:focus { border-color: #2563eb; box-shadow: 0 0 0 2px rgba(37,99,235,0.1); }
.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.form-actions { display: flex; gap: 0.5rem; }
.sub-form { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 0.375rem; padding: 1rem; margin-bottom: 1rem; display: flex; flex-direction: column; gap: 0.75rem; }
.btn-primary { background: #2563eb; color: #fff; border: none; padding: 0.5rem 1rem; border-radius: 0.375rem; cursor: pointer; font-weight: 500; font-size: 0.875rem; }
.btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-secondary { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; padding: 0.5rem 1rem; border-radius: 0.375rem; cursor: pointer; font-size: 0.875rem; }
.btn-sm { padding: 0.375rem 0.75rem; }
.btn-add { background: transparent; color: #2563eb; border: 1px solid #2563eb; padding: 0.375rem 0.75rem; border-radius: 0.375rem; cursor: pointer; font-size: 0.8125rem; }
.btn-danger-sm { background: transparent; color: #dc2626; border: none; cursor: pointer; font-size: 0.8125rem; }
.btn-danger { background: #dc2626; color: #fff; border: none; padding: 0.5rem 1rem; border-radius: 0.375rem; cursor: pointer; }
.item-list { list-style: none; padding: 0; display: flex; flex-direction: column; gap: 0.5rem; }
.item-row { display: flex; justify-content: space-between; align-items: center; padding: 0.75rem; border: 1px solid #e2e8f0; border-radius: 0.375rem; }
.text-muted { color: #64748b; }
.text-sm { font-size: 0.8125rem; }
.empty-hint { color: #94a3b8; font-size: 0.875rem; padding: 1rem 0; }
.success-msg { background: #f0fdf4; color: #16a34a; padding: 0.5rem 0.75rem; border-radius: 0.375rem; font-size: 0.875rem; margin-bottom: 0.75rem; border: 1px solid #bbf7d0; }
.state-panel { padding: 2rem; text-align: center; border-radius: 0.5rem; }
.state-panel.loading { color: #2563eb; background: #eff6ff; }
.state-panel.error { color: #b91c1c; background: #fef2f2; border: 1px solid #fecaca; }
.retry-btn { margin-left: 0.75rem; color: #2563eb; background: none; border: 1px solid #2563eb; padding: 0.25rem 0.75rem; border-radius: 0.375rem; cursor: pointer; }
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.4); display: flex; align-items: center; justify-content: center; z-index: 50; }
.modal-card { background: #fff; padding: 1.5rem; border-radius: 0.5rem; min-width: 320px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); }
.modal-actions { display: flex; gap: 0.5rem; margin-top: 1rem; justify-content: flex-end; }
.skills-summary-card { background: #fdfefe; }
.section-desc { font-size: 0.8125rem; color: #64748b; margin: 0.25rem 0 0 0; }
.btn-manage-cv-skills { display: inline-flex; align-items: center; font-size: 0.8125rem; font-weight: 600; color: #2563eb; text-decoration: none; background-color: #eff6ff; border: 1px solid #bfdbfe; padding: 0.35rem 0.75rem; border-radius: 0.375rem; transition: all 0.15s; }
.btn-manage-cv-skills:hover { background-color: #dbeafe; }
.skills-wrap { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-top: 0.5rem; }
.skill-badge { display: inline-block; background-color: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; font-size: 0.8125rem; font-weight: 500; padding: 0.25rem 0.65rem; border-radius: 9999px; }
.empty-skills-box { background-color: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 0.375rem; padding: 1.5rem; text-align: center; margin-top: 0.5rem; }
.btn-upload-cv-cta { display: inline-block; margin-top: 0.5rem; background-color: #2563eb; color: #ffffff !important; font-size: 0.8125rem; font-weight: 500; padding: 0.35rem 0.85rem; border-radius: 0.375rem; text-decoration: none; }
.btn-upload-cv-cta:hover { background-color: #1d4ed8; }
</style>
