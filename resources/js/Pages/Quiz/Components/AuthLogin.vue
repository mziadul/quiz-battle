<script setup>
import { ref } from 'vue';
import axios from 'axios';

const emit = defineEmits(['login-success', 'back']);

const email = ref('');
const password = ref('');
const authError = ref('');
const isLoading = ref(false);

const handleLogin = async () => {
    authError.value = '';
    isLoading.value = true;
    try {
        const response = await axios.post('/api/login', {
            email: email.value,
            password: password.value
        });

        // 🔑 'token' এবং 'user' চেক করা হচ্ছে
        const token = response.data.token;
        const user = response.data.user;

        if (token && user) {
            localStorage.setItem('auth_token', token);
            localStorage.setItem('user_data', JSON.stringify(user));
            axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
            
            emit('login-success', user);
        } else {
            authError.value = 'Invalid login response structure.';
        }
    } catch (err) {
        authError.value = err.response?.data?.message || 'Login failed. Please check credentials.';
    } finally {
        isLoading.value = false;
    }
};
</script>

<template>
    <div class="h-full flex flex-col justify-between p-6 bg-white overflow-y-auto">
        <div class="pt-6 space-y-2 text-center">
            <div class="w-16 h-16 bg-indigo-100 text-indigo-600 rounded-2xl mx-auto flex items-center justify-center text-3xl font-black mb-2">
                👑
            </div>
            <h2 class="text-2xl font-black text-slate-800">Welcome Back!</h2>
            <p class="text-xs text-slate-500">Sign in to start playing Quiz Battle</p>
        </div>

        <form @submit.prevent="handleLogin" class="space-y-4 my-auto">
            <div v-if="authError" class="p-3 bg-red-50 border border-red-200 text-red-600 rounded-xl text-xs font-medium text-center">
                {{ authError }}
            </div>

            <div>
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-wider block mb-1">Email</label>
                <input v-model="email" type="email" required placeholder="player@example.com" class="w-full p-3.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-800 focus:outline-none focus:border-indigo-600" />
            </div>

            <div>
                <label class="text-[10px] font-black text-slate-400 uppercase tracking-wider block mb-1">Password</label>
                <input v-model="password" type="password" required placeholder="••••••••" class="w-full p-3.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-800 focus:outline-none focus:border-indigo-600" />
            </div>

            <button type="submit" :disabled="isLoading" class="w-full py-4 bg-indigo-600 hover:bg-indigo-700 text-white rounded-2xl font-bold text-sm shadow-xl shadow-indigo-200 transition-all flex items-center justify-center gap-2">
                <span v-if="isLoading" class="animate-spin">⏳</span>
                <span v-else>Sign In & Start Playing &rsaquo;</span>
            </button>
        </form>

        <div class="text-center pb-4">
            <button @click="emit('back')" class="text-xs font-bold text-indigo-600">
                &lsaquo; Back to Intro
            </button>
        </div>
    </div>
</template>