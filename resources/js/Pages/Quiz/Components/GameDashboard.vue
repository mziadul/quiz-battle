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

const onlineUsersList = ref([]);
const recentOpponents = ref([]);
const categories = ref([]);

const showCategoryModal = ref(false);
const showFriendBattleModal = ref(false);
const incomingChallenge = ref(null);

const friendEmail = ref('');
const selectedCategoryId = ref(null);

const isWaitingOpponent = ref(false);
const isLoading = ref(false);
const activeCategoryId = ref(null);
let pollInterval = null;

onMounted(() => {
    const token = localStorage.getItem('auth_token');
    if (token) {
        axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    }

    if (window.Echo) {
        window.Echo.join('online-users')
            .here((users) => {
                onlineUsersList.value = users;
            })
            .joining((user) => {
                const exists = onlineUsersList.value.some(u => String(u.id) === String(user.id));
                if (!exists) {
                    onlineUsersList.value.push(user);
                }
            })
            .leaving((user) => {
                onlineUsersList.value = onlineUsersList.value.filter(u => String(u.id) !== String(user.id));
            })
            .error((error) => {
                console.error('Presence Channel Error:', error);
            });

        if (props.user && props.user.id) {
            window.Echo.private(`App.Models.User.${props.user.id}`)
                .listen('.FriendChallengeReceived', (e) => {
                    if (e && e.challengeData) {
                        incomingChallenge.value = e.challengeData;
                    }
                });
        }
    }

    fetchRecentOpponents();
    fetchCategories();
});

const isUserOnline = (userId) => {
    return onlineUsersList.value.some(u => Number(u.id) === Number(userId));
};

const fetchRecentOpponents = async () => {
    try {
        const res = await axios.get('/api/quiz/recent-opponents');
        if (res.data?.opponents) {
            recentOpponents.value = res.data.opponents;
        }
    } catch (err) {
        console.error('Fetch recent opponents error:', err);
    }
};

const fetchCategories = async () => {
    try {
        isLoading.value = true;
        const res = await axios.get('/api/quiz/categories');
        categories.value = Array.isArray(res.data) ? res.data : res.data.categories || [];
    } catch (err) {
        console.error('Fetch categories error:', err);
    } finally {
        isLoading.value = false;
    }
};

// 🔑 ফিক্সড ম্যাচ লজিক (শুধুমাত্র MATCH_FOUND হলেই রিডাইরেক্ট)
const isMatchReady = (resData) => {
    return resData?.status === 'MATCH_FOUND';
};

const openCategoryModal = () => {
    showCategoryModal.value = true;
};

const selectCategoryAndJoin = async (categoryId) => {
    showCategoryModal.value = false;
    isWaitingOpponent.value = true;
    activeCategoryId.value = categoryId;

    try {
        const res = await axios.post('/api/quiz/join-queue', {
            category_id: categoryId
        });

        const sessionId = res.data?.session_id || res.data?.session?.id;

        if (isMatchReady(res.data) && sessionId) {
            stopPolling();
            isWaitingOpponent.value = false;
            router.visit(`/app/quiz/match/${sessionId}`);
        } else {
            startPolling(categoryId);
        }
    } catch (err) {
        isWaitingOpponent.value = false;
        alert(err.response?.data?.message || 'Failed to join queue.');
    }
};

const sendFriendChallenge = async () => {
    if (!friendEmail.value || !selectedCategoryId.value) {
        alert('Please provide an email and select a category.');
        return;
    }

    showFriendBattleModal.value = false;
    isWaitingOpponent.value = true;

    try {
        const res = await axios.post('/api/quiz/friend-challenge/send', {
            email: friendEmail.value,
            category_id: selectedCategoryId.value
        });

        if (res.data?.session_id) {
            startPolling(selectedCategoryId.value);
        }
    } catch (err) {
        isWaitingOpponent.value = false;
        alert(err.response?.data?.message || 'Failed to send challenge.');
    }
};

const respondToChallenge = async (action) => {
    if (!incomingChallenge.value) return;

    const sessionId = incomingChallenge.value.session_id;

    try {
        const res = await axios.post(`/api/quiz/friend-challenge/${sessionId}/respond`, {
            action: action
        });

        if (action === 'accept' && res.data?.status === 'MATCH_FOUND') {
            incomingChallenge.value = null;
            router.visit(`/app/quiz/match/${sessionId}`);
        } else {
            incomingChallenge.value = null;
        }
    } catch (err) {
        incomingChallenge.value = null;
        console.error('Respond to challenge error:', err);
    }
};

const startPolling = (categoryId) => {
    stopPolling();
    pollInterval = setInterval(async () => {
        try {
            const res = await axios.post('/api/quiz/join-queue', {
                category_id: categoryId
            });

            const sessionId = res.data?.session_id || res.data?.session?.id;

            if (isMatchReady(res.data) && sessionId) {
                stopPolling();
                isWaitingOpponent.value = false;
                router.visit(`/app/quiz/match/${sessionId}`);
            }
        } catch (err) {
            console.error('Polling error:', err);
        }
    }, 1500);
};

const cancelQueue = async () => {
    stopPolling();
    isWaitingOpponent.value = false;
    try {
        await axios.post('/api/quiz/cancel-queue');
    } catch (err) {
        console.error('Cancel queue error:', err);
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
    if (window.Echo) {
        window.Echo.leave('online-users');
        if (props.user?.id) {
            window.Echo.leave(`App.Models.User.${props.user.id}`);
        }
    }
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
            <!-- Quick Match Card -->
            <div @click="openCategoryModal" class="bg-gradient-to-r from-indigo-600 to-indigo-500 rounded-3xl p-5 text-white shadow-xl relative overflow-hidden group cursor-pointer">
                <div class="relative z-10 space-y-3">
                    <span class="px-2.5 py-0.5 rounded-full bg-indigo-400/30 backdrop-blur-md text-[10px] font-bold tracking-wide">
                        🟢 {{ onlineUsersList.length }} online
                    </span>
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
            <div @click="showFriendBattleModal = true" class="bg-gradient-to-r from-lime-400 to-lime-300 rounded-3xl p-5 text-slate-900 shadow-lg relative overflow-hidden group cursor-pointer">
                <div class="relative z-10 space-y-3">
                    <div>
                        <p class="text-[10px] uppercase tracking-wider font-extrabold text-slate-700">PLAY TOGETHER</p>
                        <h3 class="text-xl font-black text-slate-900">Battle a Friend</h3>
                        <p class="text-xs text-slate-700 mt-1">Send a challenge using friend's email.</p>
                    </div>
                    <button class="inline-flex items-center gap-1 text-xs font-black bg-slate-900 text-white px-4 py-2 rounded-xl shadow-md">Challenge Friend &rsaquo;</button>
                </div>
                <div class="absolute -right-2 -bottom-2 text-6xl opacity-30 group-hover:scale-110 transition-transform">👥</div>
            </div>

            <!-- Recent Opponents Section -->
            <div class="space-y-3 pt-2">
                <div class="flex justify-between items-center">
                    <span class="text-xs font-black text-slate-400 uppercase tracking-wider">Recent Battles</span>
                </div>

                <div v-if="recentOpponents.length === 0" class="text-xs text-slate-400 italic">
                    No recent opponents yet. Play a Quick Match!
                </div>

                <div v-else class="grid grid-cols-2 gap-3">
                    <div v-for="opp in recentOpponents.slice(0, 2)" :key="opp.id" class="p-3 bg-white rounded-2xl border border-slate-100 flex items-center space-x-3 shadow-sm">
                        <div class="relative">
                            <div class="w-10 h-10 rounded-xl bg-indigo-600 text-white font-black text-xs flex items-center justify-center">
                                {{ opp.avatar_text }}
                            </div>
                            <span 
                                :class="isUserOnline(opp.id) ? 'bg-emerald-500' : 'bg-slate-300'"
                                class="w-3 h-3 border-2 border-white rounded-full absolute -bottom-0.5 -right-0.5"
                            ></span>
                        </div>

                        <div class="overflow-hidden">
                            <p class="text-xs font-bold text-slate-800 truncate">{{ opp.name }}</p>
                            <p :class="isUserOnline(opp.id) ? 'text-emerald-600' : 'text-slate-400'" class="text-[10px] font-medium">
                                {{ isUserOnline(opp.id) ? 'Online' : 'Offline' }}
                            </p>
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

        <!-- Category Selection Modal -->
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

        <!-- Battle a Friend Modal -->
        <div v-if="showFriendBattleModal" class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-end justify-center">
            <div class="bg-white w-full rounded-t-3xl p-6 space-y-4 animate-in slide-in-from-bottom duration-200">
                <div class="flex justify-between items-center">
                    <h3 class="text-lg font-black text-slate-800">Battle a Friend</h3>
                    <button @click="showFriendBattleModal = false" class="text-slate-400 hover:text-slate-600 font-bold text-sm">✕</button>
                </div>

                <div class="space-y-3">
                    <label class="text-xs font-bold text-slate-600 block">Friend Email</label>
                    <input 
                        v-model="friendEmail" 
                        type="email" 
                        placeholder="friend@example.com" 
                        class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-800 focus:outline-indigo-600"
                    />

                    <label class="text-xs font-bold text-slate-600 block pt-2">Select Category</label>
                    <select 
                        v-model="selectedCategoryId" 
                        class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-xs font-bold text-slate-800 focus:outline-indigo-600"
                    >
                        <option :value="null" disabled>Choose topic...</option>
                        <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                    </select>

                    <button 
                        @click="sendFriendChallenge" 
                        class="w-full py-3 mt-2 bg-indigo-600 hover:bg-indigo-700 text-white font-black text-xs rounded-xl shadow-lg"
                    >
                        Send Challenge 🚀
                    </button>
                </div>
            </div>
        </div>

        <!-- Incoming Challenge Modal -->
        <div v-if="incomingChallenge" class="absolute inset-0 bg-slate-900/80 backdrop-blur-md z-50 flex items-center justify-center p-6">
            <div class="bg-white rounded-3xl p-6 text-center space-y-4 w-full max-w-xs shadow-2xl">
                <div class="w-16 h-16 bg-lime-100 text-lime-600 rounded-full mx-auto flex items-center justify-center text-2xl font-black">
                    ⚔️
                </div>
                <div>
                    <h3 class="text-lg font-black text-slate-800">Battle Challenge!</h3>
                    <p class="text-xs text-slate-500 mt-1">
                        <strong class="text-indigo-600">{{ incomingChallenge.challenger_name }}</strong> challenged you to a quiz battle!
                    </p>
                </div>
                <div class="flex gap-2">
                    <button @click="respondToChallenge('decline')" class="flex-1 py-3 bg-slate-100 text-slate-600 rounded-xl font-bold text-xs">Decline</button>
                    <button @click="respondToChallenge('accept')" class="flex-1 py-3 bg-indigo-600 text-white rounded-xl font-black text-xs shadow-md">Accept ⚔️</button>
                </div>
            </div>
        </div>

        <!-- Searching for Opponent Modal -->
        <div v-if="isWaitingOpponent" class="absolute inset-0 bg-slate-900/80 backdrop-blur-md z-50 flex items-center justify-center p-6">
            <div class="bg-white rounded-3xl p-6 text-center space-y-4 w-full max-w-xs shadow-2xl animate-in zoom-in-95 duration-200">
                <div class="w-16 h-16 bg-indigo-50 text-indigo-600 rounded-full mx-auto flex items-center justify-center text-2xl font-black animate-spin">
                    ⚔️
                </div>
                <div>
                    <h3 class="text-lg font-black text-slate-800">Waiting for Opponent...</h3>
                    <p class="text-xs text-slate-500 mt-1">Waiting for opponent to accept or join the game.</p>
                </div>
                <button @click="cancelQueue" class="w-full py-3 bg-slate-100 hover:bg-slate-200 text-slate-600 rounded-xl font-extrabold text-xs">
                    Cancel
                </button>
            </div>
        </div>
    </div>
</template>