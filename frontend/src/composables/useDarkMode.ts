import { useThemeStore } from '@/stores/theme';
import { storeToRefs } from 'pinia';

export function useDarkMode() {
  const store = useThemeStore();
  const { isDark } = storeToRefs(store);

  return {
    isDark,
    toggleDark: store.toggleDark,
    initDark: store.initTheme
  };
}
