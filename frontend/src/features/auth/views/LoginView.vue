<script setup lang="ts">
import { ref } from 'vue'
import { Mail, Lock, ArrowRight, UserPlus } from 'lucide-vue-next'

const email = ref('')
const password = ref('')
const rememberMe = ref(false)

function handleLogin() {
  alert('Iniciando sesión con: ' + email.value)
}
</script>

<template>
  <div class="min-h-screen bg-background dark:bg-dark-background font-sans text-text dark:text-dark-text flex flex-col transition-colors duration-300">
    <!-- Mini Navbar -->
    <nav class="p-8">
      <router-link to="/" class="inline-flex items-center gap-3 group">
        <div class="h-10 w-10 rounded-xl bg-gradient-to-br from-primary to-primary/80 flex items-center justify-center text-white font-bold shadow-lg shadow-primary/20 group-hover:scale-110 transition-transform">
          A
        </div>
        <span class="text-2xl font-black tracking-tighter bg-clip-text text-transparent bg-gradient-to-r from-primary to-primary/60">
          ArticleMind
        </span>
      </router-link>
    </nav>

    <main class="flex-1 flex items-center justify-center p-6 -mt-12">
      <div class="w-full max-w-[440px] animate-in fade-in zoom-in-95 duration-500">
        <!-- Login Card -->
        <div class="bg-surface dark:bg-dark-surface rounded-[2.5rem] shadow-2xl shadow-primary/10 border border-secondary/10 dark:border-dark-border overflow-hidden ring-1 ring-black/5 dark:ring-white/5">
          <div class="p-10 sm:p-14">
            <header class="mb-10 text-center sm:text-left">
              <h1 class="text-4xl font-black tracking-tight mb-3">
                Bienvenido
              </h1>
              <p class="text-sm text-secondary/70 dark:text-dark-text/50 font-medium">
                Accede a tu cuenta para gestionar tus noticias.
              </p>
            </header>

            <form @submit.prevent="handleLogin" class="space-y-6">
              <div class="space-y-2">
                <label class="block text-[10px] font-black text-secondary/50 dark:text-dark-text/30 uppercase tracking-[0.2em] px-1">Email</label>
                <div class="relative group">
                  <div class="absolute inset-y-0 left-0 pl-5 flex items-center pointer-events-none text-secondary/30 group-focus-within:text-primary transition-colors">
                    <Mail class="w-4 h-4" />
                  </div>
                  <input 
                    v-model="email"
                    type="email" 
                    required
                    placeholder="nombre@ejemplo.com"
                    class="w-full bg-background dark:bg-dark-background border-0 ring-1 ring-secondary/10 dark:ring-dark-border rounded-2xl pl-12 pr-5 py-4 text-sm focus:ring-2 focus:ring-primary focus:outline-none transition-all placeholder:text-secondary/30 dark:placeholder:text-dark-text/20"
                  />
                </div>
              </div>

              <div class="space-y-2">
                <div class="flex justify-between items-center px-1">
                  <label class="block text-[10px] font-black text-secondary/50 dark:text-dark-text/30 uppercase tracking-[0.2em]">Contraseña</label>
                  <a href="#" class="text-[10px] font-bold text-primary hover:text-primary/80 transition-colors uppercase tracking-widest">¿La olvidaste?</a>
                </div>
                <div class="relative group">
                  <div class="absolute inset-y-0 left-0 pl-5 flex items-center pointer-events-none text-secondary/30 group-focus-within:text-primary transition-colors">
                    <Lock class="w-4 h-4" />
                  </div>
                  <input 
                    v-model="password"
                    type="password" 
                    required
                    placeholder="••••••••"
                    class="w-full bg-background dark:bg-dark-background border-0 ring-1 ring-secondary/10 dark:ring-dark-border rounded-2xl pl-12 pr-5 py-4 text-sm focus:ring-2 focus:ring-primary focus:outline-none transition-all placeholder:text-secondary/30 dark:placeholder:text-dark-text/20"
                  />
                </div>
              </div>

              <div class="flex items-center gap-3 px-1 pt-1">
                <div class="relative flex items-center">
                  <input 
                    v-model="rememberMe"
                    id="remember" 
                    type="checkbox" 
                    class="w-5 h-5 rounded-lg border-secondary/20 dark:border-dark-border text-primary focus:ring-primary/20 transition-all cursor-pointer appearance-none bg-background dark:bg-dark-background ring-1 ring-secondary/10 dark:ring-dark-border checked:bg-primary checked:ring-primary"
                  />
                  <svg class="w-3 h-3 text-white absolute left-1 pointer-events-none opacity-0 transition-opacity" :class="{'opacity-100': rememberMe}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="4">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                  </svg>
                </div>
                <label for="remember" class="text-xs font-bold text-secondary dark:text-dark-text/50 cursor-pointer select-none">Recordar mi cuenta</label>
              </div>

              <button 
                type="submit"
                class="group w-full py-5 bg-primary text-white text-sm font-black rounded-2xl hover:bg-primary/90 transition-all shadow-xl shadow-primary/20 flex items-center justify-center gap-2 hover:scale-[1.02] active:scale-[0.98] mt-4"
              >
                Entrar en ArticleMind
                <ArrowRight class="w-4 h-4 group-hover:translate-x-1 transition-transform" />
              </button>
            </form>

            <div class="mt-12 text-center">
              <p class="text-[10px] font-black text-secondary/40 dark:text-dark-text/20 uppercase tracking-[0.2em] mb-4">
                ¿No tienes cuenta?
              </p>
              <!-- <router-link 
                to="/register" 
                class="inline-flex items-center gap-2 px-6 py-3 rounded-xl border border-primary/20 dark:border-primary/40 text-primary font-bold text-xs hover:bg-primary/5 transition-all group"
              >
                <UserPlus class="w-4 h-4" />
                Registrate gratis
              </router-link> -->
            </div>
          </div>
          
          <!-- Bottom Accent -->
          <div class="h-1.5 bg-gradient-to-r from-primary via-primary/80 to-accent"></div>
        </div>
        
        <!-- Support link -->
        <p class="mt-10 text-center text-[10px] font-bold text-secondary/30 dark:text-dark-text/20 uppercase tracking-widest">
          &copy; 2026 ArticleMind — Hecho con IA Generativa
        </p>
      </div>
    </main>
  </div>
</template>

<style scoped>
@reference "../../../assets/main.css";

.animate-in {
  animation-fill-mode: forwards;
}
</style>
