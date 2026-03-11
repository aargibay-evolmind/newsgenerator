import { createRouter, createWebHistory } from 'vue-router'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      name: 'Generator',
      component: () => import('@/features/articles/views/ArticleGeneratorView.vue'),
    },
  ],
})

export default router
