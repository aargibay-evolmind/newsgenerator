import { createRouter, createWebHistory } from 'vue-router'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      name: 'SavedArticles',
      component: () => import('@/features/articles/views/SavedArticlesView.vue'),
    },
    {
      path: '/generador',
      name: 'Generator',
      component: () => import('@/features/articles/views/ArticleGeneratorView.vue'),
    },
    {
      path: '/login',
      name: 'Login',
      component: () => import('@/features/auth/views/LoginView.vue'),
    },
    {
      path: '/register',
      name: 'Register',
      component: () => import('@/features/auth/views/RegisterView.vue'),
    },
    {
      path: '/mis-noticias/:id',
      name: 'SavedArticleDetail',
      component: () => import('@/features/articles/views/SavedArticleDetailView.vue'),
    },
    {
      path: '/mis-noticias/:id/editar',
      name: 'EditSavedArticle',
      component: () => import('@/features/articles/views/EditSavedArticleView.vue'),
    },
  ],
})

export default router
