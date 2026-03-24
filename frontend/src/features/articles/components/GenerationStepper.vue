<script setup lang="ts">
defineProps<{
  modelValue: number;
}>();

const emit = defineEmits<{
  (e: 'update:modelValue', value: number): void;
}>();

const steps = [
  { id: 1, label: 'Definición' },
  { id: 2, label: 'Diseño' },
  { id: 3, label: 'Revisar' },
];

function goToStep(stepId: number) {
  emit('update:modelValue', stepId);
}
</script>

<template>
  <div class="w-full bg-background dark:bg-dark-background border-b border-secondary/10 dark:border-dark-border py-3 shrink-0 transition-colors duration-300">
    <div class="max-w-xl mx-auto flex items-center justify-center gap-3 px-6">
      <template v-for="(step, index) in steps" :key="step.id">
        <!-- Step Item -->
        <button 
          @click="goToStep(step.id)"
          :disabled="step.id > modelValue + 1 && modelValue < 3"
          class="flex flex-col items-center group transition-all duration-300 disabled:opacity-40"
        >
          <div class="flex items-center gap-2">
            <!-- Indicator Dot -->
            <div 
              class="w-2.5 h-2.5 rounded-full transition-all duration-500"
              :class="[
                modelValue === step.id 
                  ? 'bg-primary ring-4 ring-primary/20 scale-110' 
                  : modelValue > step.id
                    ? 'bg-primary'
                    : 'bg-secondary/20 dark:bg-dark-surface group-hover:bg-secondary/30 dark:group-hover:bg-dark-border'
              ]"
            ></div>
            
            <span 
              class="text-[10px] uppercase tracking-widest font-black transition-colors"
              :class="[
                 modelValue === step.id ? 'text-primary' : 'text-secondary/40 dark:text-dark-text/30'
              ]"
            >
              {{ step.label }}
            </span>
          </div>
        </button>

        <!-- Connector line -->
        <div 
          v-if="index < steps.length - 1"
          class="w-8 sm:w-16 h-[1px] bg-secondary/10 dark:bg-dark-border"
        >
          <div 
            class="h-full bg-primary transition-all duration-700 ease-in-out"
            :style="{ width: modelValue > step.id ? '100%' : '0%' }"
          ></div>
        </div>
      </template>
    </div>
  </div>
</template>

<style scoped>
@reference "../../../assets/main.css";
</style>
