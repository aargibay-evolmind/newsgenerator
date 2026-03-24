<script setup lang="ts">
import { Crepe } from "@milkdown/crepe";
import { Milkdown, MilkdownProvider, useEditor } from "@milkdown/vue";
import { onMounted, onUnmounted, watch, defineComponent, h } from "vue";

// IMPORTANT: Correct theme paths
import "@milkdown/crepe/theme/common/style.css";
import "@milkdown/crepe/theme/classic.css"; 

const props = defineProps<{
  modelValue: string;
}>();

const emit = defineEmits<{
  (e: 'update:modelValue', value: string): void;
}>();

// Wrapper component to access useEditor within MilkdownProvider
const InnerEditor = defineComponent({
  name: 'InnerEditor',
  components: { Milkdown },
  props: ['modelValue'],
  emits: ['update:modelValue'],
  setup(compProps, { emit: compEmit }) {
    const { get } = useEditor((root) => {
      const crepe = new Crepe({ 
        root,
        defaultValue: compProps.modelValue,
      });

      // Real-time synchronization
      crepe.on((api) => {
        api.markdownUpdated((_ctx, markdown) => {
          if (markdown !== compProps.modelValue) {
            compEmit('update:modelValue', markdown);
          }
        });
      });

      return crepe;
    });

    return () => h('div', { class: 'milkdown-inner-flex flex-1 min-h-0 flex flex-col' }, [
      h(Milkdown)
    ]);
  }
});
</script>

<template>
  <div class="milkdown-container crepe h-full w-full bg-background flex flex-col min-h-0">
    <MilkdownProvider>
      <InnerEditor 
        :model-value="modelValue"
        @update:model-value="v => emit('update:modelValue', v)"
      />
    </MilkdownProvider>
  </div>
</template>

<style scoped>
@reference "../../../assets/main.css";

/* 
  Layout for the Editor:
  The .milkdown div is the actual scroll container.
  All parents must be flex column with min-h-0 to constrain height.
*/

.milkdown-container {
  @apply flex-1 flex flex-col min-h-0 w-full bg-background overflow-visible;
}

.milkdown-inner-flex {
  @apply flex-1 flex flex-col min-h-0;
}

/* 
  The .milkdown div rendered by Milkdown is the scroll container.
  It needs overflow-y: auto to enable mousewheel scrolling.
*/
:deep(.milkdown),
:deep([data-milkdown-root="true"]) {
  flex: 1;
  min-height: 0;
  overflow-y: auto !important;
  overflow-x: hidden;
  position: relative;
}

:deep(.milkdown .ProseMirror) {
  @apply leading-relaxed text-slate-800 dark:text-dark-text outline-none;
  padding: 3rem 1.5rem !important; /* Slightly less padding to gain space */
  min-height: 100%;
  max-width: 680px !important; /* Further reduced to ensure it fits comfortably */
  margin: 0 auto !important;
  font-size: 16px;
}

/* Toolbar style match */
:deep(.milkdown .milkdown-menu) {
  @apply border-b border-slate-100 dark:border-dark-border bg-background dark:bg-dark-surface flex-shrink-0 sticky top-0 z-10;
}
</style>
