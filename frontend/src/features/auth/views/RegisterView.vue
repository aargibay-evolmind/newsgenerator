<script setup lang="ts">
import { ref } from 'vue'
import { User, Mail, Lock, ShieldCheck, ArrowRight, LogIn } from 'lucide-vue-next'

const name = ref('')
const email = ref('')
const password = ref('')
const confirmPassword = ref('')

function handleRegister() {
  alert('Registrando a: ' + name.value)
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

    <main class="flex-1 flex items-center justify-center p-6 -mt-8">
      <div class="w-full max-w-[480px] animate-in fade-in zoom-in-95 duration-500">
        <!-- Register Card -->
        <div class="bg-surface dark:bg-dark-surface rounded-[2.5rem] shadow-2xl shadow-primary/10 border border-secondary/10 dark:border-dark-border overflow-hidden ring-1 ring-black/5 dark:ring-white/5">
          <div class="p-10 sm:p-14">
            <header class="mb-10 text-center sm:text-left">
              <h1 class="text-4xl font-black tracking-tight mb-3">
                Únete a <span class="text-primary">ArticleMind</span>
              </h1>
              <p class="text-sm text-secondary/70 dark:text-dark-text/50 font-medium">
                Crea tu cuenta gratuita y empieza a generar noticias con IA.
              </p>
            </header>

            <form @submit.prevent="handleRegister" class="space-y-5">
              <div class="space-y-2">
                <label class="block text-[10px] font-black text-secondary/50 dark:text-dark-text/30 uppercase tracking-[0.2em] px-1">Nombre Completo</label>
                <div class="relative group">
                  <div class="absolute inset-y-0 left-0 pl-5 flex items-center pointer-events-none text-secondary/30 group-focus-within:text-primary transition-colors">
                    <User class="w-4 h-4" />
                  </div>
                  <input 
                    v-model="name"
                    type="text" 
                    required
                    placeholder="Tu nombre"
                    class="w-full bg-background dark:bg-dark-background border-0 ring-1 ring-secondary/10 dark:ring-dark-border rounded-2xl pl-12 pr-5 py-3.5 text-sm focus:ring-2 focus:ring-primary focus:outline-none transition-all placeholder:text-secondary/30 dark:placeholder:text-dark-text/20"
                  />
                </div>
              </div>

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
                    class="w-full bg-background dark:bg-dark-background border-0 ring-1 ring-secondary/10 dark:ring-dark-border rounded-2xl pl-12 pr-5 py-3.5 text-sm focus:ring-2 focus:ring-primary focus:outline-none transition-all placeholder:text-secondary/30 dark:placeholder:text-dark-text/20"
                  />
                </div>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="space-y-2">
                  <label class="block text-[10px] font-black text-secondary/50 dark:text-dark-text/30 uppercase tracking-[0.2em] px-1">Contraseña</label>
                  <div class="relative group">
                    <div class="absolute inset-y-0 left-0 pl-5 flex items-center pointer-events-none text-secondary/30 group-focus-within:text-primary transition-colors">
                      <Lock class="w-4 h-4" />
                    </div>
                    <input 
                      v-model="password"
                      type="password" 
                      required
                      placeholder="••••••••"
                      class="w-full bg-background dark:bg-dark-background border-0 ring-1 ring-secondary/10 dark:ring-dark-border rounded-2xl pl-12 pr-5 py-3.5 text-sm focus:ring-2 focus:ring-primary focus:outline-none transition-all placeholder:text-secondary/30 dark:placeholder:text-dark-text/20"
                    />
                  </div>
                </div>

                <div class="space-y-2">
                  <label class="block text-[10px] font-black text-secondary/50 dark:text-dark-text/30 uppercase tracking-[0.2em] px-1">Confirmar</label>
                  <div class="relative group">
                    <div class="absolute inset-y-0 left-0 pl-5 flex items-center pointer-events-none text-secondary/30 group-focus-within:text-primary transition-colors">
                      <ShieldCheck class="w-4 h-4" />
                    </div>
                    <input 
                      v-model="confirmPassword"
                      type="password" 
                      required
                      placeholder="••••••••"
                      class="w-full bg-background dark:bg-dark-background border-0 ring-1 ring-secondary/10 dark:ring-dark-border rounded-2xl pl-12 pr-5 py-3.5 text-sm focus:ring-2 focus:ring-primary focus:outline-none transition-all placeholder:text-secondary/30 dark:placeholder:text-dark-text/20"
                    />
                  </div>
                </div>
              </div>

              <div class="flex items-start gap-3 px-1 pt-2">
                <p class="text-[10px] leading-relaxed text-secondary/60 dark:text-dark-text/40 font-medium">
                  Al registrarte, aceptas nuestros <a href="#" class="text-primary hover:underline font-bold">Términos de Servicio</a> y <a href="#" class="text-primary hover:underline font-bold">Política de Privacidad</a>.
                </p>
              </div>

              <button 
                type="submit"
                class="group w-full py-4 bg-primary text-white text-sm font-black rounded-2xl hover:bg-primary/90 transition-all shadow-xl shadow-primary/20 flex items-center justify-center gap-2 hover:scale-[1.02] active:scale-[0.98] mt-4"
              >
                Crear Cuenta
                <ArrowRight class="w-4 h-4 group-hover:translate-x-1 transition-transform" />
              </button>
            </form>

            <div class="mt-12 text-center">
              <p class="text-[10px] font-black text-secondary/40 dark:text-dark-text/20 uppercase tracking-[0.2em] mb-4">
                ¿Ya tienes una cuenta?
              </p>
              <!-- <router-link 
                to="/login" 
                class="inline-flex items-center gap-2 px-6 py-3 rounded-xl border border-primary/20 dark:border-primary/40 text-primary font-bold text-xs hover:bg-primary/5 transition-all group"
              >
                <LogIn class="w-4 h-4" />
                Iniciar sesión
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
