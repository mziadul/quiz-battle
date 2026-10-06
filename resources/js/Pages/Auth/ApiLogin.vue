<script setup>
import { ref } from 'vue';
import axios from 'axios';

const email = ref('admin@email.com');
const password = ref('password');
const errorMessage = ref('');
const isLoading = ref(false);

const handleLogin = async () => {
    isLoading.value = true;
    errorMessage.value = '';

    try {
        const response = await axios.post('/api/login', {
            email: email.value,
            password: password.value
        });

        if (response.data.token) {
            localStorage.setItem('auth_token', response.data.token);
            localStorage.setItem('user_data', JSON.stringify(response.data.user));

            window.location.href = '/app/dashboard';
        }
    } catch (error) {
        errorMessage.value = error.response?.data?.message || 'Login failed. Invalid credentials.';
    } finally {
        isLoading.value = false;
    }
};
</script>

<template>
    <div class="min-h-screen bg-slate-100 text-slate-900 flex items-center justify-center p-4">
        <div class="max-w-md w-full bg-white rounded-3xl shadow-2xl p-8 border border-slate-200 space-y-6">
            
            <div class="text-center space-y-2">
                <div class="inline-flex p-3 rounded-2xl bg-indigo-50 text-indigo-600 text-3xl">
                    ⚡
                </div>
                <h2 class="text-2xl font-black text-slate-900 tracking-wide">Quiz Battle Login</h2>
                <p class="text-xs text-slate-500 font-medium">Sanctum Token Authentication</p>
            </div>

            <div v-if="errorMessage" class="bg-red-50 border border-red-200 p-3 rounded-xl text-red-600 text-sm font-bold text-center">
                {{ errorMessage }}
            </div>

            <form @submit.prevent="handleLogin" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Email Address</label>
                    <input 
                        v-model="email" 
                        type="email" 
                        required 
                        placeholder="admin@email.com"
                        class="w-full bg-slate-50 border border-slate-300 text-slate-900 rounded-xl shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm p-3 font-semibold"
                    />
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Password</label>
                    <input 
                        v-model="password" 
                        type="password" 
                        required 
                        placeholder="••••••••"
                        class="w-full bg-slate-50 border border-slate-300 text-slate-900 rounded-xl shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm p-3 font-semibold"
                    />
                </div>

                <button 
                    type="submit" 
                    :disabled="isLoading" 
                    class="w-full bg-indigo-600 hover:bg-indigo-700 active:scale-95 text-white font-black py-3.5 rounded-xl shadow-md transition-all text-sm cursor-pointer disabled:opacity-50"
                >
                    <span v-if="isLoading">Authenticating...</span>
                    <span v-else>Login & Get Token 🚀</span>
                </button>
            </form>

        </div>
    </div>
</template>