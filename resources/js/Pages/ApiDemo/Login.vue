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
        // 🚀 আপনার বিদ্যমান /api/login এন্ডপয়েন্ট হিট করা
        const response = await axios.post('/api/login', {
            email: email.value,
            password: password.value
        });

        if (response.data.token) {
            // টোকেন ও ইউজার ডাটা localStorage-এ সেভ করা (React Native-এর AsyncStorage-এর মতো)
            localStorage.setItem('auth_token', response.data.token);
            localStorage.setItem('user_data', JSON.stringify(response.data.user));

            // সফল লগইনের পর API Dashboard-এ নেভিগেট করা
            window.location.href = '/api-demo/dashboard';
        }
    } catch (error) {
        errorMessage.value = error.response?.data?.message || 'Login failed. Invalid credentials.';
    } finally {
        isLoading.value = false;
    }
};
</script>

<template>
    <div class="min-h-screen bg-slate-900 flex items-center justify-center p-4">
        <div class="max-w-md w-full bg-slate-800 rounded-3xl shadow-2xl p-8 border border-slate-700 space-y-6">
            
            <!-- Header Section -->
            <div class="text-center space-y-2">
                <div class="inline-flex p-3 rounded-2xl bg-indigo-500/10 text-indigo-400 text-3xl">
                    ⚡
                </div>
                <h2 class="text-2xl font-black text-white tracking-wide">Quiz Battle API Auth</h2>
                <p class="text-xs text-slate-400">Pure API Token Authentication UI</p>
            </div>

            <!-- Error Banner -->
            <div v-if="errorMessage" class="bg-red-500/10 border border-red-500/30 p-3 rounded-xl text-red-400 text-sm font-semibold text-center">
                {{ errorMessage }}
            </div>

            <!-- Login Form -->
            <form @submit.prevent="handleLogin" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Email Address</label>
                    <input 
                        v-model="email" 
                        type="email" 
                        required 
                        placeholder="admin@email.com"
                        class="w-full bg-slate-900 border-slate-700 text-white rounded-xl shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm p-3"
                    />
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Password</label>
                    <input 
                        v-model="password" 
                        type="password" 
                        required 
                        placeholder="••••••••"
                        class="w-full bg-slate-900 border-slate-700 text-white rounded-xl shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm p-3"
                    />
                </div>

                <button 
                    type="submit" 
                    :disabled="isLoading" 
                    class="w-full bg-indigo-600 hover:bg-indigo-500 active:scale-95 text-white font-bold py-3.5 rounded-xl shadow-lg transition-all text-sm cursor-pointer disabled:opacity-50"
                >
                    <span v-if="isLoading">Authenticating...</span>
                    <span v-else>Login & Get Sanctum Token 🚀</span>
                </button>
            </form>

        </div>
    </div>
</template>