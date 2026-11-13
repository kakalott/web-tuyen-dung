<template>
  <div class="forbidden-page">
    <div class="card">
      <h1>403</h1>
      <h2>Truy cập bị từ chối</h2>
      <p>Bạn không có quyền truy cập vào tài nguyên này.</p>
      <router-link to="/jobs" class="btn-home">Về trang chủ việc làm</router-link>
    </div>
  </div>
</template>

<style scoped>
.forbidden-page {
  display: flex;
  justify-content: center;
  align-items: center;
  padding: 4rem 1rem;
}
.card {
  text-align: center;
  max-width: 480px;
}
h1 {
  font-size: 4rem;
  font-weight: 800;
  color: #dc2626;
  margin: 0;
}
h2 {
  font-size: 1.5rem;
  color: #1e293b;
  margin: 0.5rem 0 1rem;
}
p {
  color: #64748b;
  margin-bottom: 1.5rem;
}
.btn-home {
  display: inline-block;
  background-color: #2563eb;
  color: #ffffff;
  padding: 0.625rem 1.25rem;
  border-radius: 0.375rem;
  text-decoration: none;
  font-weight: 500;
}
</style>
