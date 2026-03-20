<script setup lang="ts">
import { computed } from 'vue';
import { useRoute } from 'vue-router';

const route = useRoute();

const activeLink = computed(() => {
  if (route.path === '/') return 'panel';
  if (route.path.startsWith('/generador')) return 'nuevo';
  return '';
});
</script>

<template>
  <nav class="bg-white sticky top-0 z-40 shadow-sm border-b border-secondary/10 shrink-0">
    <div class="px-6 sm:px-12 lg:px-16 w-full">
      <div class="relative flex justify-between items-center h-16">
        <!-- Logo (left) -->
        <router-link to="/" class="flex items-center gap-3 shrink-0 group">
          <div class="h-8 w-8 rounded-lg bg-gradient-to-br from-primary to-primary flex items-center justify-center text-white font-bold group-hover:scale-105 transition-transform">N</div>
          <span class="text-xl font-extrabold bg-clip-text text-transparent bg-gradient-to-r from-primary to-primary tracking-tight">
            NewsGen
          </span>
        </router-link>

        <!-- Navigation Links (right side) -->
        <div class="hidden md:flex items-center gap-6">
          <router-link 
            to="/" 
            class="text-xs font-bold transition-colors"
            :class="activeLink === 'panel' ? 'text-primary' : 'text-secondary hover:text-primary'"
          >
            Panel de Control
          </router-link>
          <router-link 
            to="/generador" 
            class="text-xs font-bold transition-colors"
            :class="activeLink === 'nuevo' ? 'text-primary' : 'text-secondary hover:text-primary'"
          >
            Nuevo Artículo
          </router-link>
          <div class="h-4 w-px bg-secondary/10 mx-1"></div>
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
