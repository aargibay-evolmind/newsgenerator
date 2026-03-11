---
trigger: always_on
---

# AI Agent Instructions: Vue 3 Feature-Driven Architecture

## Role and Objective
You are an expert Vue 3 developer. Your goal is to write clean, scalable, and maintainable frontend code using **Feature-Driven Architecture (FDA)**. You will strictly adhere to the project's technology stack: **Vue 3 (Composition API), Vite, TanStack Query (Vue Query), Vue Router, and Pinia.** *Note: This project does not require authentication. Do not generate auth guards, token interceptors, or login flows.*

---

## 1. Core Philosophy: Feature-Driven Architecture
Do not group files by their technical type (e.g., putting all APIs in one folder, all stores in another). Instead, group files by their **business domain or feature** (e.g., `users`, `products`, `orders`). 

A "Feature" should be as self-contained as possible. If a feature is deleted, its related UI, API calls, and types should disappear without breaking the rest of the application (unless explicitly shared).

### Standard Directory Structure
Adhere to this folder structure when generating or modifying code:

```text
src/
├── app/                  # App-level setup (router.ts, main.ts, queryClient setup)
├── assets/               # Static assets (images, fonts)
├── components/           # Generic, purely presentational UI (Buttons, Cards, Inputs)
├── composables/          # Global composables (e.g., useWindowSize)
├── lib/                  # HTTP client setup and third-party library configurations
├── features/             # ⭐️ WHERE BUSINESS LOGIC LIVES ⭐️
│   ├── [feature-name]/   # E.g., `products`, `invoices`
│   │   ├── api/          # Pure CRUD functions (fetch calls)
│   │   ├── components/   # UI components specific to this feature
│   │   ├── composables/  # TanStack Query wrappers (useProducts.ts)
│   │   ├── store/        # Pinia stores (ONLY for client state related to feature)
│   │   ├── types/        # TypeScript interfaces and types
│   │   └── views/        # Page-level components used by Vue Router
└── utils/                # Pure helper functions (formatters, parsers)
```

---

## 2. Rules for State Management

### Server State (TanStack Query)
**Rule:** All data originating from the CRUD API MUST be managed by TanStack Query. 
* **Do not** use Pinia to store fetched database records.
* **Do not** manually track `isLoading`, `isError`, or `data` refs for API calls.

**Workflow for Server State:**
1.  **API Layer:** Write pure async functions returning promises in `features/[feature]/api/index.ts`.
2.  **Composable Layer:** Wrap those API calls in Vue Query's `useQuery` or `useMutation` inside `features/[feature]/composables/`.
3.  **UI Layer:** Call the composable in the Vue component.

### Client State (Pinia)
**Rule:** Pinia is strictly reserved for **Client-Side State** that needs to be shared across multiple components or features.
* **Use Pinia for:** UI state (sidebar open/closed), multi-step form data before submission, local user preferences (dark mode).
* Structure Pinia stores using the Composition API (`defineStore` with `ref` and `computed`).

---

## 3. Rules for Component Design

* **Views (`features/[feature]/views/`):** These are routed pages. They are "Smart Components." They should call the Vue Query composables to fetch data and pass that data down as props.
* **Feature Components (`features/[feature]/components/`):** Specific to the domain. They can be smart or dumb depending on the need.
* **Global Components (`src/components/`):** Must be completely "Dumb." They should never import from the `features/` directory, make API calls, or know about business logic. They only accept props and emit events.

---

## 4. Code Generation Example

When asked to create a new feature (e.g., "Todos"), follow this exact sequence:

**1. Create Types (`features/todos/types/index.ts`)**
```typescript
export interface Todo {
  id: string;
  title: string;
  completed: boolean;
}
```

**2. Create API Requests (`features/todos/api/index.ts`)**
```typescript
import { apiClient } from '@/lib/apiClient';
import type { Todo } from '../types';

export const TodoAPI = {
  getAll: () => apiClient<Todo[]>('/todos'),
  create: (data: Omit<Todo, 'id'>) => apiClient<Todo>('/todos', { method: 'POST', body: JSON.stringify(data) }),
};
```

**3. Create Query Composables (`features/todos/composables/useTodos.ts`)**
```typescript
import { useQuery, useMutation, useQueryClient } from '@tanstack/vue-query';
import { TodoAPI } from '../api';
import type { Todo } from '../types';

export function useTodos() {
  return useQuery({
    queryKey: ['todos'],
    queryFn: TodoAPI.getAll,
  });
}

export function useCreateTodo() {
  const queryClient = useQueryClient();
  
  return useMutation({
    mutationFn: TodoAPI.create,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['todos'] });
    },
  });
}
```

**4. Create the View (`features/todos/views/TodosList.vue`)**
```vue
<script setup lang="ts">
import { useTodos, useCreateTodo } from '../composables/useTodos';
import BaseButton from '@/components/BaseButton.vue'; // Global dumb component

const { data: todos, isLoading, isError } = useTodos();
const { mutate: createTodo } = useCreateTodo();

const handleAdd = () => {
  createTodo({ title: 'New Task', completed: false });
};
</script>

<template>
  <div>
    <h1>Todos</h1>
    <div v-if="isLoading">Loading...</div>
    <div v-else-if="isError">Error loading todos.</div>
    <ul v-else>
      <li v-for="todo in todos" :key="todo.id">{{ todo.title }}</li>
    </ul>
    <BaseButton @click="handleAdd">Add Todo</BaseButton>
  </div>
</template>
```

**5. Register the Route (`src/app/router.ts`)**
```typescript
import { createRouter, createWebHistory } from 'vue-router';

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/todos',
      name: 'Todos',
      component: () => import('@/features/todos/views/TodosList.vue'),
    },
  ],
});

export default router;
```