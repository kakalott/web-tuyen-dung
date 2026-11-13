<template>
  <div class="not-found-page">
    <div class="card">
      <h1>404</h1>
      <h2>Không tìm thấy trang</h2>
      <p>Trang bạn đang tìm kiếm không tồn tại hoặc đã được chuyển đi.</p>
      <router-link to="/jobs" class="btn-home">Về trang việc làm</router-link>
    </div>
  </div>
</template>

<style scoped>
.not-found-page {
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
  color: #2563eb;
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
