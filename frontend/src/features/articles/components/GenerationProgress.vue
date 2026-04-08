<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';

const props = defineProps<{
  needsInfographic?: boolean;
  resultStatus?: 'success' | 'placeholder' | 'error' | null;
}>();

const progress = ref(0);

const messages = computed(() => {
  const base = [
    "Analizando estructura conceptual...",
    "Consultando fuentes de conocimiento...",
    "Redactando contenido optimizado...",
    "Generando metadatos SEO..."
  ];

  if (props.needsInfographic) {
    base.push("Nanobanana: Generando ilustración descriptiva...");
    
    if (props.resultStatus === 'success') {
      base.push("Nanobanana: Infografía generada con éxito ✅");
    } else if (props.resultStatus === 'placeholder') {
      base.push("Nanobanana: Algo ha fallado, usando marcador de posición ⚠️");
    } else if (props.resultStatus === 'error') {
       base.push("Error crítico en el proceso ❌");
    }
  }

  base.push("Finalizando los últimos detalles...");
  return base;
});

// Sync message index with progress percentage
const currentMessageIndex = computed(() => {
  const total = messages.value.length;
  
  // Terminal state: always show the message reflecting the outcome
  if (props.resultStatus) {
    const sIndex = messages.value.findIndex(m => m.includes('✅') || m.includes('⚠️') || m.includes('❌'));
    return sIndex !== -1 ? sIndex : total - 1;
  }

  // Linear thresholds
  // We reserve the last message ("Finalizando...") for when we hit 90%+ 
  // and the status messages for 100%
  if (progress.value < 20) return 0;
  if (progress.value < 40) return 1;
  if (progress.value < 60) return 2;
  if (progress.value < 80) return 3;
  
  // If infographic is needed, it takes the 80-95% slot
  if (props.needsInfographic && progress.value < 95) return 4;
  
  // Default final stretch
  return props.needsInfographic ? 5 : 4;
});

let progressInterval: number | undefined;

onMounted(() => {
  // Simulate progress
  progressInterval = window.setInterval(() => {
    // If we have an error, stop progress
    if (props.resultStatus === 'error') {
      if (progressInterval) clearInterval(progressInterval);
      return;
    }

    if (progress.value < 30) {
      progress.value += Math.random() * 5;
    } else if (progress.value < 85) {
      progress.value += Math.random() * 2;
    } else if (progress.value < 98) {
      progress.value += 0.1;
    }
    
    // Smoothly jump to 100 on result
    if (props.resultStatus && progress.value < 100) {
      progress.value = Math.min(100, progress.value + 5);
    }
  }, 1000);
});

// Force progress to 100 when result arrives
watch(() => props.resultStatus, (newStatus) => {
  if (newStatus) {
    progress.value = 100;
  }
});

onUnmounted(() => {
  if (progressInterval) clearInterval(progressInterval);
});
</script>

<template>
  <div class="fixed inset-0 z-[100] flex flex-col items-center justify-center bg-background/90 dark:bg-dark-background/90 backdrop-blur-xl transition-all duration-500">
    <div class="max-w-md w-full px-8 text-center space-y-12">
      
      <!-- Visual Loader Group -->
      <div class="relative flex flex-col items-center">
        <!-- Main Circular Loader -->
        <div class="relative w-32 h-32 flex items-center justify-center">
          <!-- Outer pulsing ring -->
          <div 
            class="absolute inset-0 rounded-full border-4 transition-colors duration-500 animate-[ping_3s_infinite]"
            :class="resultStatus === 'error' ? 'border-red-500/20' : resultStatus === 'placeholder' ? 'border-amber-500/20' : 'border-primary/20 dark:border-primary/10'"
          ></div>
          
          <!-- Middle rotating ring -->
          <div 
            class="absolute inset-0 rounded-full border-t-4 transition-colors duration-500"
            :class="[
              resultStatus === 'error' ? 'border-red-500' : resultStatus === 'placeholder' ? 'border-amber-500' : 'border-primary',
              resultStatus ? '' : 'animate-spin'
            ]"
          ></div>
          
          <!-- Inner static circle -->
          <div class="relative w-24 h-24 rounded-full bg-white dark:bg-dark-surface shadow-2xl flex items-center justify-center border border-secondary/10 dark:border-dark-border">
            <svg 
               v-if="resultStatus === 'error'"
               class="h-10 w-10 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <svg 
               v-else-if="resultStatus === 'placeholder'"
               class="h-10 w-10 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <svg 
              v-else
              class="h-10 w-10 text-primary transition-colors duration-500" 
              :class="!resultStatus && 'animate-pulse'"
              fill="none" viewBox="0 0 24 24" stroke="currentColor"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
            </svg>
          </div>
        </div>

        <!-- Floating Particles Effect -->
        <div 
          class="absolute -z-10 blur-3xl w-64 h-64 rounded-full animate-pulse top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 transition-colors duration-1000"
          :class="resultStatus === 'error' ? 'bg-red-500/10' : resultStatus === 'placeholder' ? 'bg-amber-500/10' : 'bg-primary/20'"
        ></div>
      </div>

      <!-- Text Content -->
      <div class="space-y-4">
        <h2 class="text-2xl font-black text-text dark:text-dark-text tracking-tight uppercase">
          {{ resultStatus === 'error' ? 'Error en el Proceso' : resultStatus ? '¡Listo!' : 'Generando...' }}
        </h2>
        <div class="h-6 flex items-center justify-center overflow-hidden">
          <transition 
            name="slide-up" 
            mode="out-in"
          >
            <p :key="currentMessageIndex" class="text-sm font-bold tracking-wide transition-colors duration-500"
               :class="[
                 (messages[currentMessageIndex] || '').includes('✅') ? 'text-green-600 dark:text-green-400' : 
                 (messages[currentMessageIndex] || '').includes('⚠️') ? 'text-amber-600 dark:text-amber-400' : 
                 (messages[currentMessageIndex] || '').includes('❌') ? 'text-red-600 dark:text-red-400' : 
                 'text-primary'
               ]"
            >
              {{ messages[currentMessageIndex] || 'Procesando...' }}
            </p>
          </transition>
        </div>
        <p v-if="!resultStatus" class="text-xs font-medium text-secondary/60 dark:text-dark-text/40 pt-2 italic">
          Esto puede tardar unos minutos...
        </p>
      </div>

      <!-- Progress Bar Container -->
      <div class="w-full space-y-2">
        <div class="h-2 w-full bg-secondary/10 dark:bg-dark-border rounded-full overflow-hidden shadow-inner border border-secondary/5 dark:border-white/5">
          <div 
            class="h-full transition-all duration-1000 ease-out shadow-[0_0_15px_rgba(var(--color-primary),0.5)]"
            :class="[
              resultStatus === 'error' ? 'bg-red-500' : resultStatus === 'placeholder' ? 'bg-amber-500' : 'bg-gradient-to-r from-primary to-primary-light'
            ]"
            :style="{ width: `${progress}%` }"
          ></div>
        </div>
        <div class="flex justify-between items-center text-[10px] font-black uppercase text-secondary/40 dark:text-dark-text/30 tracking-widest px-1">
          <span>{{ resultStatus === 'error' ? 'Interrumpido' : 'Progreso' }}</span>
          <span>{{ Math.round(progress) }}%</span>
        </div>
      </div>

    </div>
  </div>
</template>

<style scoped>
@reference "../../../assets/main.css";

.slide-up-enter-active,
.slide-up-leave-active {
  transition: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
}

.slide-up-enter-from {
  opacity: 0;
  transform: translateY(10px);
}

.slide-up-leave-to {
  opacity: 0;
  transform: translateY(-10px);
}

@keyframes ping {
  75%, 100% {
    transform: scale(1.5);
    opacity: 0;
  }
}
</style>
