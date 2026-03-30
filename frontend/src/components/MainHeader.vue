<script setup lang="ts">
import { computed, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { Sun, Moon } from 'lucide-vue-next';
import { useDarkMode } from '../composables/useDarkMode';

const route = useRoute();
const { isDark, toggleDark, initDark } = useDarkMode();

onMounted(() => {
  initDark();
});

const activeLink = computed(() => {
  if (route.path === '/') return 'panel';
  if (route.path.startsWith('/generador')) return 'nuevo';
  return '';
});
</script>

<template>
  <nav class="bg-background dark:bg-dark-background sticky top-0 z-40 shadow-sm border-b border-secondary/10 dark:border-dark-border shrink-0 transition-colors duration-300">
    <div class="px-6 sm:px-12 lg:px-16 w-full">
      <div class="relative flex justify-between items-center h-16">
        <!-- Logo (left) -->
        <router-link to="/" class="flex items-center gap-3 shrink-0 group">
          <div class="h-10 w-10 rounded-xl bg-gradient-to-br from-primary to-primary/80 flex items-center justify-center text-white font-bold shadow-lg shadow-primary/20 group-hover:scale-110 transition-transform tracking-tight">
            A
          </div>
          <span class="text-2xl font-black tracking-tighter bg-clip-text text-transparent bg-gradient-to-r from-primary to-primary/60">
            ArticleMind
          </span>
        </router-link>

        <!-- Navigation Links (right side) -->
        <div class="hidden md:flex items-center gap-6">
          <button 
            @click="toggleDark" 
            class="p-2 rounded-xl bg-secondary/5 hover:bg-secondary/10 dark:bg-dark-surface dark:hover:bg-dark-border text-secondary dark:text-dark-text transition-all active:scale-90"
            :title="isDark ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro'"
          >
            <Sun v-if="isDark" class="w-4 h-4" />
            <Moon v-else class="w-4 h-4" />
          </button>

          <router-link 
            to="/" 
            class="text-xs font-bold transition-colors"
            :class="activeLink === 'panel' ? 'text-primary' : 'text-secondary dark:text-dark-text/70 hover:text-primary dark:hover:text-primary'"
          >
            Panel de Control
          </router-link>
          <router-link 
            to="/generador" 
            class="text-xs font-bold transition-colors"
            :class="activeLink === 'nuevo' ? 'text-primary' : 'text-secondary dark:text-dark-text/70 hover:text-primary dark:hover:text-primary'"
          >
            Nuevo Artículo
          </router-link>
          <div class="h-4 w-px bg-secondary/10 dark:bg-dark-border mx-1"></div>
          <router-link 
            to="/login" 
            class="px-4 py-2 bg-primary text-white text-[10px] font-black uppercase tracking-widest rounded-xl hover:bg-primary/90 transition-all shadow-lg shadow-primary/10 hover:scale-105 active:scale-95"
          >
            Iniciar Sesión
          </router-link>
        </div>

        <!-- Mobile Menu Placeholder -->
        <div class="md:hidden flex items-center">
          <router-link to="/login" class="text-[10px] font-black text-primary uppercase tracking-widest">Login</router-link>
        </div>
      </div>
    </div>
  </nav>
</template>

<style scoped>
@reference "../assets/main.css";
</style>
