<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
    user: Object
});

const emit = defineEmits(['logout']);

const userPoints = ref(5840);
const userLevel = ref(12);

// Modal & Matchmaking States
const categories = ref([]);
const showCategoryModal = ref(false);
const isWaitingOpponent = ref(false);
const isLoading = ref(false);
const activeCategoryId = ref(null);
let pollInterval = null;

// Axios Interceptor for Sanctum Token Setup
onMounted(() => {
    const token = localStorage.getItem('auth_token');
    if (token) {
        axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    }
});

// 🔑 Helper check for any match status
const isMatchReady = (resData) => {
    const status = resData?.status;
    return status === 'MATCH_FOUND' || status === 'MATCHED' || status === 'SUCCESS';
};

// 1. Fetch Categories
const openCategoryModal = async () => {
    showCategoryModal.value = true;
    if (categories.value.length === 0) {
        try {
            isLoading.value = true;
            const res = await axios.get('/api/quiz/categories');
            categories.value = Array.isArray(res.data) ? res.data : res.data.categories || [];
        } catch (err) {
            console.error('Error loading categories:', err);
        } finally {
            isLoading.value = false;
        }
    }
};

// 2. Start Matchmaking / Join Queue
const selectCategoryAndJoin = async (categoryId) => {
    showCategoryModal.value = false;
    isWaitingOpponent.value = true;
    activeCategoryId.value = categoryId;

    try {
        const res = await axios.post('/api/quiz/join-queue', {
            category_id: categoryId
        });

        const sessionId = res.data?.session_id || res.data?.session?.id;

        // ২য় প্লেয়ার যদি সাথে সাথে ম্যাচ পেয়ে যায়
        if (isMatchReady(res.data) && sessionId) {
            stopPolling();
            isWaitingOpponent.value = false;
            router.visit(`/app/quiz/match/${sessionId}`);
        } else {
            // ১ম প্লেয়ার ওয়েটিং-এ থাকলে পোলিং শুরু হবে
            startPolling(categoryId);
        }
    } catch (err) {
        console.error('Queue Join Error:', err);
        isWaitingOpponent.value = false;
        alert(err.response?.data?.message || 'Failed to join queue.');
    }
};

// 3. Fast & Reliable Polling Backup while Waiting (1.2 seconds)
const startPolling = (categoryId) => {
    stopPolling();
    pollInterval = setInterval(async () => {
        try {
            const res = await axios.post('/api/quiz/join-queue', {
                category_id: categoryId
            });

            const sessionId = res.data?.session_id || res.data?.session?.id;

            // ২য় প্লেয়ার জয়েন করার পর ১ম প্লেয়ারের পোলিং হিট হিট হলেই সাথে সাথে অ্যারেনায় পাঠাবে
            if (isMatchReady(res.data) && sessionId) {
                stopPolling();
                isWaitingOpponent.value = false;
                router.visit(`/app/quiz/match/${sessionId}`);
            }
        } catch (err) {
            console.error('Polling Error:', err);
        }
    }, 1200); // 👈 ১২০০ms (১.২ সে.) দেওয়া হয়েছে দ্রুত সিঙ্ক হওয়ার জন্য
};

// 4. Cancel Queue
const cancelQueue = async () => {
    stopPolling();
    isWaitingOpponent.value = false;
    try {
        await axios.post('/api/quiz/cancel-queue');
    } catch (err) {
        console.error('Cancel Queue Error:', err);
    }
};

const stopPolling = () => {
    if (pollInterval) {
        clearInterval(pollInterval);
        pollInterval = null;
    }
};

onUnmounted(() => {
    stopPolling();
});
</script>

<template>
    <div class="h-full flex flex-col justify-between bg-slate-50 overflow-y-auto relative">
        <!-- Top Header -->
        <div class="p-6 pb-2 space-y-4 bg-white rounded-b-3xl shadow-sm">
            <div class="flex justify-between items-center">
                <h1 class="text-xl font-black text-indigo-600 tracking-tight">Quiz<span class="text-amber-500">Battle</span></h1>
                <div class="flex items-center space-x-2">
                    <span class="px-3 py-1 bg-amber-100 text-amber-700 rounded-full text-xs font-black flex items-center gap-1">⚡ 7</span>
                    <button @click="emit('logout')" title="Logout" class="w-9 h-9 rounded-full bg-indigo-600 text-white font-bold flex items-center justify-center text-xs shadow-md uppercase">
                        {{ user?.name?.substring(0, 2) || 'AA' }}
                    </button>
                </div>
            </div>

            <div class="flex justify-between items-center pt-2">
                <div>
                    <p class="text-[10px] font-extrabold text-indigo-500 uppercase tracking-wider">READY TO PLAY, {{ user?.name || 'PLAYER' }}?</p>
                    <h2 class="text-2xl font-black text-slate-800 leading-tight">Ready to make<br/>your brain buzz?</h2>
                </div>
                <div class="relative w-16 h-16 flex items-center justify-center bg-indigo-50 rounded-full border-4 border-indigo-500 shadow-inner">
                    <div class="text-center">
                        <span class="text-lg font-black text-indigo-700 block leading-none">{{ userLevel }}</span>
                        <span class="text-[8px] font-bold text-indigo-400 uppercase">LEVEL</span>
                    </div>
                </div>
            </div>

            <!-- Points Card -->
            <div class="p-4 bg-slate-50 border border-slate-100 rounded-2xl flex justify-between items-center">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-500 flex items-center justify-center text-xl font-bold">⭐</div>
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase">TOTAL POINTS</p>
                        <p class="text-lg font-black text-slate-800">{{ userPoints.toLocaleString() }} <span class="text-xs text-slate-400">pts</span></p>
                    </div>
                </div>
                <button class="px-3 py-1.5 bg-indigo-50 text-indigo-600 rounded-xl font-bold text-xs hover:bg-indigo-100">
                    View winning &rsaquo;
                </button>
            </div>
        </div>

        <!-- Content Area -->
        <div class="p-6 space-y-4">
            <!-- Quick Match Card (Opens Category Modal) -->
            <div @click="openCategoryModal" class="bg-gradient-to-r from-indigo-600 to-indigo-500 rounded-3xl p-5 text-white shadow-xl relative overflow-hidden group cursor-pointer">
                <div class="relative z-10 space-y-3">
                    <span class="px-2.5 py-0.5 rounded-full bg-indigo-400/30 backdrop-blur-md text-[10px] font-bold tracking-wide">🟢 260 online</span>
                    <div>
                        <p class="text-[10px] uppercase tracking-wider font-extrabold text-indigo-200">PLAY NOW</p>
                        <h3 class="text-xl font-black">Quick Match</h3>
                        <p class="text-xs text-indigo-100 opacity-90 mt-1">Select a category and battle instantly.</p>
                    </div>
                    <span class="inline-flex items-center gap-1 text-xs font-black bg-white text-indigo-600 px-4 py-2 rounded-xl shadow-md">Select Category &rsaquo;</span>
                </div>
                <div class="absolute -right-2 -bottom-2 text-6xl opacity-30 group-hover:scale-110 transition-transform">⚔️</div>
            </div>

            <!-- Battle a Friend Card -->
            <div class="bg-gradient-to-r from-lime-400 to-lime-300 rounded-3xl p-5 text-slate-900 shadow-lg relative overflow-hidden group cursor-pointer">
                <div class="relative z-10 space-y-3">
                    <div class="flex space-x-1">
                        <span class="w-6 h-6 rounded-full bg-pink-400 text-white font-black text-[10px] flex items-center justify-center border-2 border-lime-300">TA</span>
                        <span class="w-6 h-6 rounded-full bg-blue-500 text-white font-black text-[10px] flex items-center justify-center border-2 border-lime-300">RF</span>
                    </div>
                    <div>
                        <p class="text-[10px] uppercase tracking-wider font-extrabold text-slate-700">PLAY TOGETHER</p>
                        <h3 class="text-xl font-black text-slate-900">Battle a Friend</h3>
                        <p class="text-xs text-slate-700 mt-1">Send a challenge or join with a room code.</p>
                    </div>
                    <button class="inline-flex items-center gap-1 text-xs font-black bg-slate-900 text-white px-4 py-2 rounded-xl shadow-md">Invite a Friend &rsaquo;</button>
                </div>
                <div class="absolute -right-2 -bottom-2 text-6xl opacity-30 group-hover:scale-110 transition-transform">👥</div>
            </div>

            <!-- Friends Online -->
            <div class="space-y-3 pt-2">
                <div class="flex justify-between items-center">
                    <span class="text-xs font-black text-slate-400 uppercase tracking-wider">Friends online now</span>
                    <button class="text-xs font-bold text-indigo-600">See all</button>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div class="p-3 bg-white rounded-2xl border border-slate-100 flex items-center space-x-3 shadow-sm">
                        <div class="relative">
                            <div class="w-10 h-10 rounded-xl bg-pink-500 text-white font-black text-xs flex items-center justify-center">TA</div>
                            <span class="w-3 h-3 bg-emerald-500 border-2 border-white rounded-full absolute -bottom-0.5 -right-0.5"></span>
                        </div>
                        <div class="overflow-hidden">
                            <p class="text-xs font-bold text-slate-800 truncate">Tania</p>
                            <p class="text-[10px] text-emerald-600 font-medium">Online</p>
                        </div>
                    </div>

                    <div class="p-3 bg-white rounded-2xl border border-slate-100 flex items-center space-x-3 shadow-sm">
                        <div class="relative">
                            <div class="w-10 h-10 rounded-xl bg-blue-500 text-white font-black text-xs flex items-center justify-center">RF</div>
                            <span class="w-3 h-3 bg-amber-500 border-2 border-white rounded-full absolute -bottom-0.5 -right-0.5"></span>
                        </div>
                        <div class="overflow-hidden">
                            <p class="text-xs font-bold text-slate-800 truncate">Rafi</p>
                            <p class="text-[10px] text-amber-600 font-medium">In battle</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bottom Navigation -->
        <div class="bg-white border-t border-slate-100 px-6 py-3 flex justify-around items-center rounded-t-3xl shadow-lg">
            <button class="flex flex-col items-center space-y-1 text-indigo-600"><span class="text-lg">🏠</span><span class="text-[10px] font-bold">Home</span></button>
            <button class="flex flex-col items-center space-y-1 text-slate-400 hover:text-indigo-600"><span class="text-lg">⚔️</span><span class="text-[10px] font-bold">Battle</span></button>
            <button class="flex flex-col items-center space-y-1 text-slate-400 hover:text-indigo-600"><span class="text-lg">🏆</span><span class="text-[10px] font-bold">Ranks</span></button>
            <button class="flex flex-col items-center space-y-1 text-slate-400 hover:text-indigo-600"><span class="text-lg">👤</span><span class="text-[10px] font-bold">Profile</span></button>
        </div>

        <!-- 🎯 1. CATEGORY SELECTION MODAL -->
        <div v-if="showCategoryModal" class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-end justify-center">
            <div class="bg-white w-full rounded-t-3xl p-6 space-y-4 animate-in slide-in-from-bottom duration-200">
                <div class="flex justify-between items-center">
                    <h3 class="text-lg font-black text-slate-800">Select Category</h3>
                    <button @click="showCategoryModal = false" class="text-slate-400 hover:text-slate-600 font-bold text-sm">✕</button>
                </div>
                <p class="text-xs text-slate-500">Choose a category to start searching for an opponent.</p>
                
                <div v-if="isLoading" class="text-center py-8 text-indigo-600 font-bold text-xs animate-pulse">
                    Loading Categories... 🎯
                </div>
                <div v-else class="space-y-2 max-h-60 overflow-y-auto">
                    <div 
                        v-for="cat in categories" 
                        :key="cat.id" 
                        @click="selectCategoryAndJoin(cat.id)" 
                        class="p-4 bg-slate-50 hover:bg-indigo-50 border border-slate-100 hover:border-indigo-200 rounded-2xl flex justify-between items-center cursor-pointer transition-all"
                    >
                        <span class="text-xs font-black text-slate-800 flex items-center gap-2">🎯 {{ cat.name }}</span>
                        <span class="text-indigo-600 font-black text-xs">&rsaquo;</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ⚔️ 2. SEARCHING FOR OPPONENT MODAL -->
        <div v-if="isWaitingOpponent" class="absolute inset-0 bg-slate-900/80 backdrop-blur-md z-50 flex items-center justify-center p-6">
            <div class="bg-white rounded-3xl p-6 text-center space-y-4 w-full max-w-xs shadow-2xl animate-in zoom-in-95 duration-200">
                <div class="w-16 h-16 bg-indigo-50 text-indigo-600 rounded-full mx-auto flex items-center justify-center text-2xl font-black animate-spin">
                    ⚔️
                </div>
                <div>
                    <h3 class="text-lg font-black text-slate-800">Finding Opponent...</h3>
                    <p class="text-xs text-slate-500 mt-1">Waiting for another player to join this category.</p>
                </div>
                <button @click="cancelQueue" class="w-full py-3 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl font-extrabold text-xs">
                    Cancel Search
                </button>
            </div>
        </div>
    </div>
</template>