<script setup lang="ts">
import { ref } from 'vue'

const email = ref('')
const password = ref('')
const rememberMe = ref(false)

function handleLogin() {
  alert('Iniciando sesión con: ' + email.value)
}
</script>

<template>
  <div class="min-h-screen bg-slate-50 font-sans text-text flex flex-col">
    <!-- Mini Navbar -->
    <nav class="p-8">
      <router-link to="/" class="inline-flex items-center gap-2 group">
        <div class="h-8 w-8 rounded-lg bg-primary flex items-center justify-center text-white font-black text-lg transition-transform group-hover:scale-110">N</div>
        <span class="text-xl font-black tracking-tighter text-secondary group-hover:text-primary transition-colors">NewsGen</span>
      </router-link>
    </nav>

    <main class="flex-1 flex items-center justify-center p-6">
      <div class="w-full max-w-md">
        <!-- Login Card -->
        <div class="bg-white rounded-3xl shadow-2xl shadow-primary/5 border border-secondary/10 overflow-hidden">
          <div class="p-8 sm:p-12">
            <h1 class="text-3xl font-black tracking-tighter mb-2 italic">Bienvenido de nuevo</h1>
            <p class="text-sm text-secondary/60 font-medium mb-10">Accede a tu cuenta para gestionar tus noticias.</p>

            <form @submit.prevent="handleLogin" class="space-y-6">
              <div>
                <label class="block text-[10px] font-black text-secondary/40 uppercase tracking-widest mb-2 px-1">Email</label>
                <input 
                  v-model="email"
                  type="email" 
                  required
                  placeholder="nombre@ejemplo.com"
                  class="w-full bg-slate-50 border-0 ring-1 ring-secondary/10 rounded-2xl px-5 py-4 text-sm focus:ring-2 focus:ring-primary focus:outline-none transition-all placeholder:text-secondary/30"
                />
              </div>

              <div>
                <div class="flex justify-between items-center mb-2 px-1">
                  <label class="block text-[10px] font-black text-secondary/40 uppercase tracking-widest leading-none">Contraseña</label>
                  <a href="#" class="text-[10px] font-bold text-primary hover:underline">¿La olvidaste?</a>
                </div>
                <input 
                  v-model="password"
                  type="password" 
                  required
                  placeholder="••••••••"
                  class="w-full bg-slate-50 border-0 ring-1 ring-secondary/10 rounded-2xl px-5 py-4 text-sm focus:ring-2 focus:ring-primary focus:outline-none transition-all placeholder:text-secondary/30"
                />
              </div>

              <div class="flex items-center gap-3 px-1">
                <input 
                  v-model="rememberMe"
                  id="remember" 
                  type="checkbox" 
                  class="w-4 h-4 rounded-md border-secondary/20 text-primary focus:ring-primary/20 transition-all cursor-pointer"
                />
                <label for="remember" class="text-xs font-bold text-secondary/60 cursor-pointer select-none">Recordar mi cuenta</label>
              </div>

              <button 
                type="submit"
                class="w-full py-5 bg-primary text-white text-sm font-black rounded-2xl hover:bg-primary/90 transition-all shadow-xl shadow-primary/20 scale-100 hover:scale-[1.02] active:scale-95 mt-4"
              >
                Entrar en NewsGen
              </button>
            </form>

            <div class="mt-10 text-center">
              <p class="text-xs font-bold text-secondary/40 uppercase tracking-wider">
                ¿No tienes cuenta? 
                <a href="#" class="text-primary hover:underline ml-1">Regístrate gratis</a>
              </p>
            </div>
          </div>
          
          <!-- Bottom Accent -->
          <div class="h-2 bg-gradient-to-r from-primary to-accent"></div>
        </div>
        
        <!-- Support link -->
        <p class="mt-8 text-center text-[10px] font-bold text-secondary/30 uppercase tracking-widest">
          &copy; 2026 NewsGen — Centro de Soporte
        </p>
      </div>
    </main>
  </div>
</template>

<style scoped>
@reference "../../../assets/main.css";
</style>
