<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted } from 'vue';
import axios from 'axios';

const authUser = usePage().props.auth.user;

const onlineUsers = ref([]);
const searchQuery = ref('');
const searchResult = ref(null);
const searchMessage = ref('');
const isSearching = ref(false);
const challengeStatus = ref('');

// Challenge & Game Session States
const incomingChallenge = ref(null);

onMounted(() => {
    console.log('--- AUTH USER ID ---:', authUser.id);

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

    // 2. Private Channel Setup (Listen for incoming challenge & acceptance)
    const privateChannel = window.Echo.private(`App.Models.User.${authUser.id}`);

    privateChannel.subscribed(() => {
        console.log(`--- SUCCESS: Subscribed to App.Models.User.${authUser.id} ---`);
    });

    // 📩 Listen for Incoming Challenge (For Player 2 - Receiver)
    privateChannel.listen('.QuizChallengeReceived', (data) => {
        console.log('--- CHALLENGE RECEIVED ---:', data);
        incomingChallenge.value = data;
    });

    // ⚔️ Listen for Challenge Accepted (For Player 1 - Challenger)
    privateChannel.listen('.QuizChallengeAccepted', (data) => {
        console.log('--- CHALLENGE ACCEPTED EVENT RECEIVED BY PLAYER 1 ---:', data);
        challengeStatus.value = '⚔️ Challenge Accepted! Redirecting to Match Arena...';

        // Player 1 (Challenger)-কে স্বয়ংক্রিয়ভাবে গেম পেজে রিডাইরেক্ট করে দেওয়া
        window.location.href = `/quiz/match/${data.gameSessionId}`;
    });
});

onUnmounted(() => {
    if (window.Echo) {
        window.Echo.leave('online-users');
        window.Echo.leave(`App.Models.User.${authUser.id}`);
    }
});

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

// Send Challenge Function
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

// Accept Challenge Function
const acceptChallenge = async () => {
    if (!incomingChallenge.value) return;

    const sessionId = incomingChallenge.value.gameSessionId;

    try {
        await axios.post(route('quiz.respondChallenge'), {
            session_id: sessionId,
            status: 'accepted'
        });

        incomingChallenge.value = null;

        // Hard Redirect Player 2 (Receiver) to Match Arena
        window.location.href = `/quiz/match/${sessionId}`;
    } catch (error) {
        console.error('Failed to accept challenge:', error);
    }
};

// Decline Challenge Function
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

                <!-- Live Online Users Section -->
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

                <!-- Search Section -->
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

                    <!-- Search Result Card -->
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