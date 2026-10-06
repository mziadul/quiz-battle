<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import axios from 'axios';

const user = ref(null);
const token = ref('');
const categories = ref([]);
const isSearchingMatch = ref(false);
const selectedCategory = ref(null);
const activeQueueSessionId = ref(null);

const getApiClient = () => {
    return axios.create({
        headers: {
            'Authorization': `Bearer ${token.value}`,
            'Accept': 'application/json'
        }
    });
};

onMounted(() => {
    token.value = localStorage.getItem('auth_token');
    const storedUser = localStorage.getItem('user_data');

    if (!token.value) {
        window.location.href = '/app/login';
        return;
    }

    if (storedUser) {
        user.value = JSON.parse(storedUser);
    }

    fetchCategories();

    if (window.Echo && user.value) {
        window.Echo.connector.options.auth.headers.Authorization = `Bearer ${token.value}`;

        window.Echo.private(`App.Models.User.${user.value.id}`)
            .listen('.MatchFound', (data) => {
                console.log('--- QUICK MATCH FOUND FOR PLAYER 1 ---:', data);
                window.location.href = `/app/quiz/match/${data.gameSessionId}`; // 👈 /app/ যোগ করা হয়েছে
            });
    }
});

onUnmounted(() => {
    if (window.Echo && user.value) {
        window.Echo.leave(`App.Models.User.${user.value.id}`);
    }
});

const fetchCategories = async () => {
    try {
        const client = getApiClient();
        const response = await client.get('/api/quiz/categories');
        categories.value = response.data;
    } catch (error) {
        console.error('Failed to load categories:', error);
        if (error.response?.status === 401) {
            logout();
        }
    }
};

const startQuickMatch = async (category) => {
    selectedCategory.value = category;
    isSearchingMatch.value = true;

    try {
        const client = getApiClient();
        const response = await client.post('/api/quiz/join-queue', {
            category_id: category.id
        });

        if (response.data.status === 'MATCH_FOUND') {
            window.location.href = `/app/quiz/match/${response.data.session_id}`; // 👈 /app/ যোগ করা হয়েছে
        } else {
            activeQueueSessionId.value = response.data.session_id;
        }
    } catch (error) {
        console.error('Error joining queue:', error);
        isSearchingMatch.value = false;
    }
};

const cancelSearching = async () => {
    if (!activeQueueSessionId.value) {
        isSearchingMatch.value = false;
        return;
    }

    try {
        const client = getApiClient();
        await client.post('/api/quiz/cancel-queue', {
            session_id: activeQueueSessionId.value
        });
    } catch (error) {
        console.error('Error cancelling queue:', error);
    } finally {
        isSearchingMatch.value = false;
        activeQueueSessionId.value = null;
        selectedCategory.value = null;
    }
};

const logout = () => {
    localStorage.removeItem('auth_token');
    localStorage.removeItem('user_data');
    window.location.href = '/app/login';
};
</script>

<template>
    <div class="min-h-screen bg-slate-100 text-slate-900 p-6">
        <div class="max-w-5xl mx-auto space-y-6">
            
            <!-- User Profile Header -->
            <div class="bg-white border border-slate-200 p-6 rounded-2xl flex justify-between items-center shadow-sm">
                <div>
                    <h1 class="text-xl font-black text-slate-900">Welcome, {{ user?.name || 'Player' }} 👋</h1>
                    <p class="text-xs text-indigo-600 font-bold font-mono mt-1">Authenticated via Sanctum Bearer Token</p>
                </div>
                <button 
                    @click="logout" 
                    class="bg-red-50 hover:bg-red-100 border border-red-200 text-red-600 px-4 py-2 rounded-xl text-xs font-black cursor-pointer transition-colors shadow-sm"
                >
                    LOGOUT
                </button>
            </div>

            <!-- Token Preview Box -->
            <div class="bg-slate-900 p-4 rounded-xl shadow border border-slate-800 space-y-1">
                <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Active Token (Stored in LocalStorage):</p>
                <p class="font-mono text-xs text-emerald-400 truncate font-semibold">{{ token }}</p>
            </div>

            <!-- Category Grid Section -->
            <div class="bg-white border border-slate-200 p-6 rounded-2xl shadow-sm space-y-4">
                <div>
                    <h3 class="text-lg font-black text-slate-900">⚡ Quick Match</h3>
                    <p class="text-xs text-slate-500 font-medium">Select a category to join the match queue</p>
                </div>

                <div v-if="categories.length > 0" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-4">
                    <button
                        v-for="cat in categories"
                        :key="cat.id"
                        @click="startQuickMatch(cat)"
                        class="p-5 bg-slate-50 border-2 border-slate-200 hover:border-indigo-600 hover:bg-indigo-50 rounded-2xl text-center transition-all flex flex-col items-center gap-2 cursor-pointer group shadow-sm"
                    >
                        <span class="text-4xl group-hover:scale-110 transition-transform">{{ cat.icon || '🧠' }}</span>
                        <span class="text-sm text-slate-900 font-black">{{ cat.name }}</span>
                    </button>
                </div>

                <div v-else class="text-slate-500 text-sm font-semibold italic animate-pulse">
                    Loading categories via /api/quiz/categories...
                </div>
            </div>

        </div>

        <!-- Searching Opponent Modal -->
        <div v-if="isSearchingMatch" class="fixed inset-0 bg-slate-900/70 backdrop-blur-sm flex items-center justify-center p-4 z-50">
            <div class="bg-white border border-slate-200 rounded-3xl p-8 max-w-sm w-full text-center shadow-2xl space-y-5">
                <div class="relative w-20 h-20 mx-auto">
                    <div class="absolute inset-0 rounded-full border-4 border-indigo-200 animate-ping"></div>
                    <div class="relative w-20 h-20 rounded-full bg-indigo-600 text-white flex items-center justify-center text-3xl font-bold shadow-lg">
                        ⚔️
                    </div>
                </div>

                <div>
                    <h3 class="text-xl font-black text-slate-900">Searching Opponent...</h3>
                    <p class="text-sm text-indigo-600 font-extrabold mt-1">Category: {{ selectedCategory?.name }}</p>
                </div>

                <p class="text-xs text-slate-500 font-medium">Waiting for a player with the same category to join...</p>

                <button 
                    @click="cancelSearching" 
                    class="w-full bg-red-50 hover:bg-red-100 text-red-600 border border-red-200 py-3 rounded-xl font-black text-xs transition-colors cursor-pointer shadow-sm"
                >
                    CANCEL SEARCH
                </button>
            </div>
        </div>
    </div>
</template>