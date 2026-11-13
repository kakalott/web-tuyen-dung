import { ref } from 'vue';

export function useAsyncState(asyncFn, initialData = null) {
  const data = ref(initialData);
  const loading = ref(false);
  const error = ref(null);
  const isAiServiceDown = ref(false);

  const execute = async (...args) => {
    loading.value = true;
    error.value = null;
    isAiServiceDown.value = false;

    try {
      const result = await asyncFn(...args);
      data.value = result;
      return result;
    } catch (err) {
      error.value = err;
      if (err?.status === 502 || err?.code === 'AI_SERVICE_ERROR') {
        isAiServiceDown.value = true;
      }
      throw err;
    } finally {
      loading.value = false;
    }
  };

  return {
    data,
    loading,
    error,
    isAiServiceDown,
    execute,
    retry: execute,
  };
}
