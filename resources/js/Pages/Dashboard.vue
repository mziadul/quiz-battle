<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted } from 'vue';
import axios from 'axios';

const authUser = usePage().props.auth.user;

// Quick Match & Categories State
const categories = ref([]);
const selectedCategory = ref(null);
const isSearchingMatch = ref(false);
const activeQueueSessionId = ref(null);

// Manual Challenge & Online Users State
const onlineUsers = ref([]);
const searchQuery = ref('');
const searchResult = ref(null);
const searchMessage = ref('');
const isSearching = ref(false);
const challengeStatus = ref('');

// Challenge Modal State
const incomingChallenge = ref(null);

// Fetch Available Categories
const fetchCategories = async () => {
    try {
        const response = await axios.get('/api/quiz/categories');
        categories.value = response.data;
    } catch (error) {
        console.error('Failed to load categories:', error);
    }
};

onMounted(() => {
    console.log('--- AUTH USER ID ---:', authUser.id);

    // Categories লোড করা
    fetchCategories();

    if (!window.Echo) return;

    // 1. Active Users Presence Channel
    window.Echo.join('online-users')
        .here((users) => { 
            console.log('--- ONLINE USERS LIST ---:', users);
            onlineUsers.value = users; 
        })
        .joining((user) => {
            console.log('--- USER JOINED ---:', user);
            if (!onlineUsers.value.some(u => u.id === user.id)) {
                onlineUsers.value.push(user);
            }
        })
        .leaving((user) => {
            console.log('--- USER LEFT ---:', user);
            onlineUsers.value = onlineUsers.value.filter(u => u.id !== user.id);
        })
        .error((error) => {
            console.error('--- PRESENCE CHANNEL ERROR ---:', error);
        });

    // 2. Private Channel Setup
    const privateChannel = window.Echo.private(`App.Models.User.${authUser.id}`);

    privateChannel.subscribed(() => {
        console.log(`--- SUCCESS: Subscribed to App.Models.User.${authUser.id} ---`);
    });

    // ⚡ Listen for Quick Match Found (When Player 1 is waiting in queue)
    privateChannel.listen('.MatchFound', (data) => {
        console.log('--- QUICK MATCH FOUND FOR PLAYER 1 ---:', data);
        window.location.href = `/quiz/match/${data.gameSessionId}`;
    });

    // 📩 Listen for Direct Incoming Challenge (Player 2)
    privateChannel.listen('.QuizChallengeReceived', (data) => {
        console.log('--- CHALLENGE RECEIVED ---:', data);
        incomingChallenge.value = data;
    });

    // ⚔️ Listen for Direct Challenge Accepted (Player 1)
    privateChannel.listen('.QuizChallengeAccepted', (data) => {
        console.log('--- CHALLENGE ACCEPTED EVENT RECEIVED BY PLAYER 1 ---:', data);
        challengeStatus.value = '⚔️️ Challenge Accepted! Redirecting to Match Arena...';
        window.location.href = `/quiz/match/${data.gameSessionId}`;
    });
});

onUnmounted(() => {
    if (window.Echo) {
        window.Echo.leave('online-users');
        window.Echo.leave(`App.Models.User.${authUser.id}`);
    }
});

// -------------------------------------------------------------
// ⚡ QUICK MATCH FUNCTIONS
// -------------------------------------------------------------
const startQuickMatch = async (category) => {
    selectedCategory.value = category;
    isSearchingMatch.value = true;

    try {
        const response = await axios.post('/api/quiz/join-queue', {
            category_id: category.id
        });

        if (response.data.status === 'MATCH_FOUND') {
            // Player 2 matched immediately -> redirect straight away
            window.location.href = `/quiz/match/${response.data.session_id}`;
        } else {
            // Player 1 put into waiting queue
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
        await axios.post('/api/quiz/cancel-queue', {
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

// -------------------------------------------------------------
// 🔍 MANUAL SEARCH & CHALLENGE FUNCTIONS
// -------------------------------------------------------------
const handleSearch = async () => {
    if (!searchQuery.value.trim()) return;
    isSearching.value = true;
    searchResult.value = null;
    searchMessage.value = '';
    challengeStatus.value = '';

    try {
        const response = await axios.post(route('quiz.search'), { query: searchQuery.value });
        if (response.data.found) {
            searchResult.value = response.data.user;
        } else {
            searchMessage.value = response.data.message;
        }
    } catch (error) {
        searchMessage.value = 'Error while searching.';
    } finally {
        isSearching.value = false;
    }
};

const isUserOnline = (userId) => onlineUsers.value.some(u => u.id === userId);

const sendChallenge = async (user) => {
    challengeStatus.value = `Sending challenge to ${user.name}...`;

    try {
        const response = await axios.post(route('quiz.sendChallenge'), {
            target_user_id: user.id
        });
        challengeStatus.value = response.data.message;
    } catch (error) {
        challengeStatus.value = error.response?.data?.message || 'Failed to send challenge.';
    }
};

const acceptChallenge = async () => {
    if (!incomingChallenge.value) return;

    const sessionId = incomingChallenge.value.gameSessionId;

    try {
        await axios.post(route('quiz.respondChallenge'), {
            session_id: sessionId,
            status: 'accepted'
        });

        incomingChallenge.value = null;
        window.location.href = `/quiz/match/${sessionId}`;
    } catch (error) {
        console.error('Failed to accept challenge:', error);
    }
};

const declineChallenge = async () => {
    if (!incomingChallenge.value) return;

    try {
        await axios.post(route('quiz.respondChallenge'), {
            session_id: incomingChallenge.value.gameSessionId,
            status: 'declined'
        });
    } catch (error) {
        console.error(error);
    } finally {
        incomingChallenge.value = null;
    }
};
</script>

<template>
    <Head title="Dashboard - Quiz Battle" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Quiz Battle Arena</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">
                
                <!-- Status Banner -->
                <div v-if="challengeStatus" class="bg-indigo-50 border-l-4 border-indigo-500 p-4 rounded shadow-sm">
                    <p class="font-bold text-indigo-700">{{ challengeStatus }}</p>
                </div>

                <!-- ⚡ 1. QUICK MATCH CATEGORY SELECTION -->
                <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                    <h3 class="text-xl font-bold text-gray-800 mb-1">⚡ Quick Match</h3>
                    <p class="text-sm text-gray-500 mb-6">Select a category to automatically match with an online player!</p>

                    <div v-if="categories.length > 0" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-4">
                        <button
                            v-for="cat in categories"
                            :key="cat.id"
                            @click="startQuickMatch(cat)"
                            class="p-5 border-2 rounded-xl text-center font-bold hover:border-indigo-600 hover:bg-indigo-50 transition-all shadow-sm flex flex-col items-center gap-2 group cursor-pointer"
                        >
                            <span class="text-4xl group-hover:scale-110 transition-transform">{{ cat.icon || '🧠' }}</span>
                            <span class="text-gray-800 text-sm font-bold">{{ cat.name }}</span>
                        </button>
                    </div>

                    <div v-else class="text-gray-400 text-sm italic animate-pulse">
                        Loading categories...
                    </div>
                </div>

                <!-- 👥 2. LIVE ONLINE USERS SECTION -->
                <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                    <h3 class="text-lg font-bold mb-4 flex items-center gap-2">
                        <span>Players Online</span>
                        <span class="bg-green-100 text-green-800 text-xs px-2.5 py-0.5 rounded-full font-semibold">
                            {{ onlineUsers.length }} Active
                        </span>
                    </h3>

                    <div v-if="onlineUsers.length > 1" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <template v-for="user in onlineUsers" :key="user.id">
                            <div v-if="user.id !== authUser.id" class="flex items-center justify-between p-3 border rounded-lg hover:border-indigo-300 transition-colors bg-gray-50">
                                <div>
                                    <p class="font-semibold text-gray-800">{{ user.name }}</p>
                                    <p class="text-xs text-gray-500">@{{ user.username || user.email }}</p>
                                </div>
                                <button
                                    @click="sendChallenge(user)"
                                    class="bg-indigo-600 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-md text-xs font-bold transition-all shadow-sm flex items-center gap-1"
                                >
                                    <span>Challenge</span>
                                    <span>⚔️</span>
                                </button>
                            </div>
                        </template>
                    </div>

                    <div v-else class="text-gray-500 text-sm italic">
                        No other players online right now. Search for a player below!
                    </div>
                </div>

                <!-- 🔎 3. SEARCH SECTION -->
                <div class="bg-white p-6 shadow-sm sm:rounded-lg">
                    <h3 class="text-lg font-bold mb-4">Find Opponent by Email/Username</h3>
                    <div class="flex gap-2 max-w-md">
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Email or Username..."
                            class="border-gray-300 rounded-md shadow-sm flex-1"
                            @keyup.enter="handleSearch"
                        />
                        <button @click="handleSearch" :disabled="isSearching" class="bg-indigo-600 text-white px-4 py-2 rounded-md font-medium text-sm">
                            {{ isSearching ? 'Searching...' : 'Search' }}
                        </button>
                    </div>

                    <div v-if="searchResult" class="mt-4 p-4 border rounded-md flex items-center justify-between bg-gray-50">
                        <div>
                            <p class="font-bold text-gray-800">{{ searchResult.name }}</p>
                            <p class="text-sm text-gray-500">@{{ searchResult.username || searchResult.email }}</p>
                            <span :class="isUserOnline(searchResult.id) ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'" class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium mt-2">
                                {{ isUserOnline(searchResult.id) ? '● Online' : '○ Offline' }}
                            </span>
                        </div>

                        <div>
                            <button
                                @click="sendChallenge(searchResult)"
                                class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-md font-medium text-sm"
                            >
                                Challenge Now ⚔️
                            </button>
                        </div>
                    </div>
                    <p v-if="searchMessage" class="mt-2 text-red-500 text-sm">{{ searchMessage }}</p>
                </div>

            </div>
        </div>

        <!-- 🔍 SEARCHING OPPONENT MODAL -->
        <div v-if="isSearchingMatch" class="fixed inset-0 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4 z-50">
            <div class="bg-white rounded-2xl p-8 max-w-sm w-full text-center shadow-2xl space-y-5">
                <div class="relative w-20 h-20 mx-auto">
                    <div class="absolute inset-0 rounded-full border-4 border-indigo-200 animate-ping"></div>
                    <div class="relative w-20 h-20 rounded-full bg-indigo-600 text-white flex items-center justify-center text-3xl font-bold shadow-lg">
                        ⚔️
                    </div>
                </div>

                <div>
                    <h3 class="text-xl font-extrabold text-gray-900">Searching for Opponent...</h3>
                    <p class="text-sm text-indigo-600 font-semibold mt-1">Category: {{ selectedCategory?.name }}</p>
                </div>

                <p class="text-xs text-gray-400">Please wait while we match you with an online player.</p>

                <button 
                    @click="cancelSearching" 
                    class="w-full bg-red-50 hover:bg-red-100 text-red-600 py-2.5 rounded-xl font-bold text-sm transition-colors border border-red-200 cursor-pointer"
                >
                    Cancel Search
                </button>
            </div>
        </div>

        <!-- 🔔 INCOMING CHALLENGE MODAL POPUP -->
        <div v-if="incomingChallenge" class="fixed inset-0 bg-black/50 flex items-center justify-center p-4 z-50">
            <div class="bg-white rounded-lg p-6 max-w-sm w-full text-center shadow-xl">
                <div class="text-4xl mb-2">⚔️</div>
                <h3 class="text-xl font-bold text-gray-900">Quiz Challenge!</h3>
                <p class="text-gray-600 text-sm mt-2">
                    <strong>{{ incomingChallenge.challenger?.name || 'A player' }}</strong> is challenging you to a quiz battle!
                </p>

                <div class="mt-6 flex gap-3 justify-center">
                    <button @click="acceptChallenge" class="bg-green-600 hover:bg-green-700 text-white px-5 py-2 rounded-md font-bold text-sm">
                        Accept Battle 🚀
                    </button>
                    <button @click="declineChallenge" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-md font-medium text-sm">
                        Decline
                    </button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>